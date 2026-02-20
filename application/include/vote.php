<?php
/**
 * @author Amin Mahmoudi (MasterkinG)
 * @copyright    Copyright (c) 2019 - 2024, MasterkinG32. (https://masterking32.com)
 * @link    https://masterking32.com
 * @TODO: Add vote verify system.
 **/

class vote
{
    public static function post_handler()
    {
        // Handle vote verification after returning from voting site
        if (get_config('vote_system') && !empty($_GET['verify_vote']) && !empty($_GET['account']) && !empty($_GET['siteid'])) {
            self::verify_vote_after_return($_GET['account'], $_GET['siteid'], !empty($_GET['character_name']) ? $_GET['character_name'] : null);
            return;
        }

        // Handle vote submission
        if (get_config('vote_system') && !empty($_POST['account']) && !empty($_POST['siteid'])) {
            self::do_vote($_POST['account'], $_POST['siteid'], !empty($_POST['character_name']) ? $_POST['character_name'] : null);
        }
    }

    /**
     * Verify vote after user returns from voting site
     * @param string $account Account name or email
     * @param int $siteID Site ID (1-based)
     * @param string|null $character_name Optional character name
     * @return void
     */
    public static function verify_vote_after_return($account, $siteID, $character_name = null)
    {
        if (!is_numeric($siteID)) {
            error_msg(lang('vote_site_not_valid'));
            return;
        }

        $siteID--; // Convert to 0-based

        if (self::verify_mmorating_vote($account, $siteID, $character_name)) {
            success_msg('Спасибо за голос! Награда выдана.');
        } else {
            error_msg('Голос не найден. Пожалуйста, убедитесь, что вы проголосовали на сайте.');
        }

        header('location: ' . get_config('baseurl'));
        exit();
    }

    /**
     * Validate account and do vote.
     * @param string $account Account name or email
     * @param int $siteID Site ID (1-based)
     * @param string|null $character_name Optional character name
     * @return bool
     */
    public static function do_vote($account, $siteID, $character_name = null)
    {
        global $antiXss;
        $vote_sites = get_config('vote_sites');
        if (!is_numeric($siteID) || empty($vote_sites[$siteID - 1])) {
            error_msg(lang('vote_site_not_valid'));
            return false;
        }

        if (get_config('battlenet_support')) {
            if (!filter_var($account, FILTER_VALIDATE_EMAIL)) {
                error_msg(lang('use_valid_email'));
                return false;
            }

            $acc_data = user::get_user_by_email($account);
        } else {
            if (!preg_match('/^[0-9A-Z-_]+$/', strtoupper($account))) {
                error_msg(lang('use_valid_username'));
                return false;
            }

            $acc_data = user::get_user_by_username($account);
        }

        if (empty($acc_data['id'])) {
            error_msg(lang('account_is_not_valid'));
            return false;
        }

        if (!isset($acc_data['votePoints'])) {
            self::setup_vote_table();
        }
        $siteID--;
        $vote_site = $vote_sites[$siteID];
        
        // Check if this is MMORating.top site with API verification
        $is_mmorating = !empty($vote_site['api_type']) && $vote_site['api_type'] === 'mmorating';
        
        database::$auth->executeStatement("DELETE FROM `votes` WHERE `votedate` < ? AND `done` = 0", [date("Y-m-d H:i:s", time() - 43200)]);

        if (!empty(self::get_vote_by_IP($siteID)) || !empty(self::get_vote_by_account($siteID, $acc_data['id']))) {
            error_msg(lang('you_already_voted'));
            return false;
        }

        // For MMORating.top, check via API before giving reward
        if ($is_mmorating) {
            $api_key = !empty($vote_site['api_key']) ? $vote_site['api_key'] : get_config('mmorating_api_key');
            if (empty($api_key)) {
                error_msg('API ключ для MMORating.top не настроен. Пожалуйста, настройте его в конфигурации.');
                return false;
            }

            // Use character name from parameter or POST
            if (empty($character_name) && !empty($_POST['character_name'])) {
                $character_name = trim($_POST['character_name']);
            }
            
            // Check vote via API
            $has_voted = self::check_mmorating_vote($api_key, $account, $character_name, getIP());
            
            if (!$has_voted) {
                // User hasn't voted yet, redirect to voting site
                // Don't record vote yet, will be checked when user returns
                header('location: ' . $vote_site['site_url']);
                exit();
            }
            
            // User has voted, record and give reward
            database::$auth->insert('votes', [
                'ip' => $antiXss->xss_clean(strtoupper(getIP())),
                'vote_site' => $antiXss->xss_clean($siteID),
                'accountid' => $antiXss->xss_clean($acc_data['id']),
                'done' => 1, // Mark as done since verified via API
            ]);

            $queryBuilder = database::$auth->createQueryBuilder();
            $queryBuilder->update('account')
                ->set('votePoints', 'votePoints + 1')
                ->where('id = :id')
                ->setParameter('id', $acc_data['id']);

            $queryBuilder->executeQuery();

            success_msg('Спасибо за голос! Награда выдана.');
            header('location: ' . get_config('baseurl'));
            exit();
        } else {
            // Standard vote flow for other sites
            database::$auth->insert('votes', [
                'ip' => $antiXss->xss_clean(strtoupper(getIP())),
                'vote_site' => $antiXss->xss_clean($siteID),
                'accountid' => $antiXss->xss_clean($acc_data['id']),
            ]);

            $queryBuilder = database::$auth->createQueryBuilder();
            $queryBuilder->update('account')
                ->set('votePoints', 'votePoints + 1')
                ->where('id = :id')
                ->setParameter('id', $acc_data['id']);

            $queryBuilder->executeQuery();

            header('location: ' . $vote_site['site_url']);
            exit();
        }
    }

    public static function get_vote_by_IP($siteID)
    {
        $queryBuilder = database::$auth->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('votes')
            ->where('ip = :ip')
            ->andWhere('vote_site = :siteid')
            ->setParameter('ip', strtoupper(getIP()))
            ->setParameter('siteid', $siteID);

        $statement = $queryBuilder->executeQuery();
        $datas = $statement->fetchAllAssociative();

        if (!empty($datas[0]['id'])) {
            return $datas;
        }

        return false;
    }

    public static function get_vote_by_account($siteID, $accountID)
    {
        $queryBuilder = database::$auth->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('votes')
            ->where('accountid = :accountid')
            ->andWhere('vote_site = :siteid')
            ->setParameter('accountid', $accountID)
            ->setParameter('siteid', $siteID);

        $statement = $queryBuilder->executeQuery();
        $datas = $statement->fetchAllAssociative();

        if (!empty($datas[0]['id'])) {
            return $datas;
        }

        return false;
    }

    /**
     * Check vote on MMORating.top via API
     * @param string $api_key API key for MMORating.top
     * @param string $account Account name or email
     * @param string|null $character_name Optional character name
     * @param string $ip_address IP address
     * @return bool
     */
    public static function check_mmorating_vote($api_key, $account, $character_name = null, $ip_address = null)
    {
        $api_url = get_config('mmorating_api_url');
        if (empty($api_url)) {
            $api_url = 'https://mmorating.top/api/v1/vote/check-flexible';
        }

        $data = [
            'api_key' => $api_key,
        ];

        // Add character name if provided
        if (!empty($character_name)) {
            $data['character_name'] = $character_name;
        }

        // Add email if battlenet support (email-based accounts)
        if (get_config('battlenet_support') && filter_var($account, FILTER_VALIDATE_EMAIL)) {
            $data['email'] = $account;
        }

        // Add IP address as fallback
        if (empty($ip_address)) {
            $ip_address = getIP();
        }
        $data['ip_address'] = $ip_address;

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !empty($curlError)) {
            // If API is unavailable, allow vote but log error
            error_log('MMORating API error: HTTP ' . $httpCode . ' - ' . $curlError);
            return false;
        }

        $result = json_decode($response, true);

        if (isset($result['success']) && $result['success'] && isset($result['has_voted']) && $result['has_voted']) {
            return true;
        }

        return false;
    }

    /**
     * Verify vote after user returns from MMORating.top
     * This can be called separately to verify votes
     * @param string $account Account name or email
     * @param int $siteID Site ID (0-based)
     * @param string|null $character_name Optional character name
     * @return bool
     */
    public static function verify_mmorating_vote($account, $siteID, $character_name = null)
    {
        $vote_sites = get_config('vote_sites');
        if (empty($vote_sites[$siteID])) {
            return false;
        }

        $vote_site = $vote_sites[$siteID];
        if (empty($vote_site['api_type']) || $vote_site['api_type'] !== 'mmorating') {
            return false;
        }

        $api_key = !empty($vote_site['api_key']) ? $vote_site['api_key'] : get_config('mmorating_api_key');
        if (empty($api_key)) {
            return false;
        }

        // Get account data
        if (get_config('battlenet_support')) {
            $acc_data = user::get_user_by_email($account);
        } else {
            $acc_data = user::get_user_by_username($account);
        }

        if (empty($acc_data['id'])) {
            return false;
        }

        // Check if already verified
        $existing_vote = self::get_vote_by_account($siteID, $acc_data['id']);
        if (!empty($existing_vote) && !empty($existing_vote[0]['done'])) {
            return true; // Already verified
        }

        // Check via API
        $has_voted = self::check_mmorating_vote($api_key, $account, $character_name, getIP());

        if ($has_voted) {
            // Record vote if not already recorded
            if (empty($existing_vote)) {
                global $antiXss;
                database::$auth->insert('votes', [
                    'ip' => $antiXss->xss_clean(strtoupper(getIP())),
                    'vote_site' => $antiXss->xss_clean($siteID),
                    'accountid' => $antiXss->xss_clean($acc_data['id']),
                    'done' => 1,
                ]);
            } else {
                // Update existing vote to mark as done
                database::$auth->executeStatement(
                    "UPDATE `votes` SET `done` = 1 WHERE `id` = ?",
                    [$existing_vote[0]['id']]
                );
            }

            // Give reward
            $queryBuilder = database::$auth->createQueryBuilder();
            $queryBuilder->update('account')
                ->set('votePoints', 'votePoints + 1')
                ->where('id = :id')
                ->setParameter('id', $acc_data['id']);

            $queryBuilder->executeQuery();

            return true;
        }

        return false;
    }

    public static function setup_vote_table()
    {
        database::$auth->executeQuery("ALTER TABLE `account` ADD COLUMN `votePoints` varchar(255) NULL DEFAULT '0';");
        database::$auth->executeQuery("
            CREATE TABLE `votes` (
                `id` bigint(255) NOT NULL AUTO_INCREMENT,
                `ip` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
                `vote_site` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
                `accountid` bigint(255) NULL DEFAULT 0,
                `votedate` timestamp(0) NULL DEFAULT current_timestamp(0),
                `done` int(10) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`) USING BTREE
            ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;
        ");

        return true;
    }
}

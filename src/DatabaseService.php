<?php


namespace UNSProjectApp;


use Exception;
use UNSProjectApp\Helpers\DataBase;

class DatabaseService
{

    const TABLE_USERS = 'unsproject_users';
    const TABLE_TICKETS = 'unsproject_tickets';
    const TABLE_SRP = 'unsproject_srp';

    /**
     * @var DataBase
     */
    private $database;

    public function __construct()
    {
        $this->database = new DataBase();
    }

    /**
     * USERS
     */
    public function createUsersTable()
    {

        $sql = "CREATE TABLE IF NOT EXISTS  `" . $this->generateTableName(self::TABLE_USERS) . "` (
                `id` BIGINT(20) NOT NULL AUTO_INCREMENT , 
                `wp_user_id` BIGINT(20) NOT NULL DEFAULT 0 ,
                `uns_user_id` VARCHAR(100) NOT NULL DEFAULT '' , 
                `attestation_type` VARCHAR(20) NOT NULL DEFAULT '',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP , 
                `updated_at` DATETIME on update CURRENT_TIMESTAMP NOT NULL ,
                PRIMARY KEY (`id`, `wp_user_id`, `uns_user_id`)
            ) ENGINE = InnoDB;";
        $this->database->query($sql)->execute();
    }

    /**
     * @param int $wpUserId
     * @param string $defaultAttestationType
     * @param null|string $unsUserId
     *
     * @return int
     */
    public function saveIntoUsersTable($wpUserId, $defaultAttestationType, $unsUserId = null)
    {
       // $this->cleanUpOldRecords($this->generateTableName(self::TABLE_USERS), 30, 'wp_user_id = 0 AND uns_user_id = ""');
        $sql =   "INSERT INTO `" . $this->generateTableName(self::TABLE_USERS) . "`"
            . " (wp_user_id, uns_user_id, attestation_type) VALUES ("
            . "'" . $this->database->sanitize($wpUserId) . "', "
            . "'" . $this->database->sanitize($unsUserId) . "',"
            . "'" . $this->database->sanitize($defaultAttestationType). "'"
            . ");";
        do_action('simple-logs', $sql, 'test');
        $this->database->query(
          $sql
        )->execute();

        return $this->database->getInsertId();
    }

    /**
     * @param string $ticketID
     * @param string $unsUserID
     * @param string $sessionId
     */
    public function updateUserByTicketId($ticketID, $unsUserID, $sessionId)
    {
        $tickets = $this->generateTableName(self::TABLE_TICKETS);
        $users = $this->generateTableName(self::TABLE_USERS);
        $query = '(SELECT users_id FROM ' . $tickets . ' WHERE ticket_id = "' . $ticketID . '" LIMIT 1)';
        $result = $this->database->query($query)->getRow();
        if (isset($result['users_id'])) {
            $users_id = $result['users_id'];
            if (empty($users_id)) {
                //TICKET GENERATED FROM Front End
                $query = "UPDATE " . $tickets
                    . " SET uns_user_id='".$this->database->sanitize($unsUserID) ."', "
                    . " session_id = '". $this->database->sanitize($sessionId) ."', "
                    ." users_id = (SELECT id FROM " . $users . " WHERE uns_user_id = '" . $this->database->sanitize($unsUserID) . "' ORDER BY id DESC LIMIT 1)";
            } else {
                //TICKET GENERATED IN BackEnd
                $query = 'UPDATE ' . $users . ' 
                SET uns_user_id = "' . $this->database->sanitize($unsUserID) . '"
                WHERE id = (
                    SELECT users_id FROM ' . $tickets . ' 
                    WHERE 
                        ticket_id = "' . $this->database->sanitize($ticketID) . '"
                        AND session_id = "'. $this->database->sanitize($sessionId).'" 
                    LIMIT 1
                ) 
                LIMIT 1';
            }
            $this->database->query($query)->execute();
        }
    }

    public function updateGuardianUrlByTicketId($ticketId, $guardianUrl)
    {
        $table = $this->generateTableName(self::TABLE_TICKETS);
        $query = 'UPDATE '. $table . '
            SET guardian_url = "'. $this->database->sanitize($guardianUrl).'",
                callback=1
            WHERE ticket_id = "'.$this->database->sanitize($ticketId).'" LIMIT 1';
        $this->database->query($query)->execute();
    }

    /**
     * @param int $wordPressUserId
     * @param SiteOptions $siteOptions
     * @return int
     */
    public function getNumberOfAccountsConnections($wordPressUserId, $siteOptions)
    {
        if ($wordPressUserId === 0) {
            return 0;
        }

        $defaultAttestationType = $siteOptions->getValue('default_attestation_type') !== null
            ? $siteOptions->getValue('default_attestation_type')
            : UnsApp::DEFAULT_ATTESTATION_TYPE;

        $lowerAttestationsString = implode(',', array_map(function ($key) {
                return sprintf('"%s"', $this->database->sanitize($key));
            }, UnsApp::getLowerAttestations($defaultAttestationType))
        );

        if(empty($lowerAttestationsString)){
            $lowerAttestationsString = '""';
        }

        $query = 'SELECT count(*) as number FROM ' . $this->generateTableName(self::TABLE_USERS)
            . ' WHERE wp_user_id =' . (int)$wordPressUserId . ' AND uns_user_id != "" '.
            ' AND attestation_type IN ( '. $lowerAttestationsString. ' )';

        $result = $this->database->query($query)->getRow();
        return isset($result['number'])
            ? (int)$result['number']
            : 0;
    }

    /**
     * @param string $ticketId
     * @return int
     * @throws Exception
     */
    public function getWordPresSUserIDByTicketID($ticketId)
    {
        $users = $this->generateTableName(self::TABLE_USERS);
        $tickets = $this->generateTableName(self::TABLE_TICKETS);

        $query = 'SELECT wp_user_id FROM ' . $users
            . ' LEFT JOIN ' . $tickets
            . ' ON ' . $tickets . '.users_id = ' . $users . '.id '
            . ' WHERE '.$tickets.'.ticket_id = "' . $this->database->sanitize($ticketId) . '" AND '.$users.'.uns_user_id != "" LIMIT 1';
        $row = $this->database->query($query)->getRow();

        if (!isset($row['wp_user_id'])) {
            return 0;
        }
        return (int)$row['wp_user_id'];
    }

    /**
     * GENERAL
     */

    /**
     * @param string $tableName
     */
    public function deleteTable($tableName)
    {
        if (strpos($tableName, $this->database->getTablePrefix()) === false) {
            $tableName = $this->generateTableName($tableName);
        }

        $this->database->query("DROP TABLE IF EXISTS `" . $tableName . "`;")->execute();
    }

    /**
     * @param string $tableName
     */
    public function truncateTable($tableName)
    {
        if (strpos($tableName, $this->database->getTablePrefix()) === false) {
            $tableName = $this->generateTableName($tableName);
        }

        $this->database->query("TRUNCATE TABLE `" . $tableName . "`;")->execute();
    }

    /**
     * @param string $tableName
     * @return string
     */
    private function generateTableName($tableName)
    {
        return $this->database->getTablePrefix() . $tableName;
    }

    /**
     * @param int $wp_user_id
     */
    public function deleteUserConnections($wp_user_id)
    {
        if (empty($wp_user_id)) {
            return;
        }

        $this->database->query('DELETE FROM ' . $this->generateTableName(self::TABLE_USERS)
            . ' WHERE wp_user_id = ' . (int) $wp_user_id . ';')
            ->execute();
    }

    /**
     * TICKETS
     */

    public function createTableTickets()
    {
        $query = "CREATE TABLE `" . $this->generateTableName(self::TABLE_TICKETS) . "` (
            `id` BIGINT(20) NOT NULL AUTO_INCREMENT ,
            `users_id` BIGINT(20) NOT NULL,
            `ticket_id` VARCHAR(200) NOT NULL,
            `uns_user_id` VARCHAR(100) NOT NULL DEFAULT '',
            `session_id` VARCHAR(100) NOT NULL DEFAULT '',
            `guardian_url` VARCHAR(200) NOT NULL DEFAULT '',
            `callback` TINYINT NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
             PRIMARY KEY (`id`,`users_id`,`ticket_id`)
        ) ENGINE = InnoDB;";

        $this->database->query($query)->execute();
    }

    /**
     * @param $ticketId
     * @param int $users_id
     * @param string $sessionId
     */
    public function saveIntoTickets($ticketId, $users_id = 0, $sessionId)
    {
        $this->database->query(
            "INSERT INTO `" . $this->generateTableName(self::TABLE_TICKETS) . "`"
            . " (ticket_id, users_id, session_id) VALUES ("
            . "'" . $this->database->sanitize($ticketId) . "', "
            . "'" . $this->database->sanitize($users_id) . "', "
            . "'" . $this->database->sanitize($sessionId) . "'"
            . ");"
        )->execute();
    }

    /**
     * @param string $ticketId
     * @param null|string $sessionId
     * @return array
     */
    public function getTicketById($ticketId, $sessionId = null){
        $query = 'SELECT * FROM ' . $this->generateTableName(self::TABLE_TICKETS)
            . ' WHERE ticket_id = "' . $this->database->sanitize($ticketId). '" '
            .($sessionId !== null ? 'AND session_id = "'.$this->database->sanitize($sessionId).'"' : '')
            .' LIMIT 1';

        return $this->database->query($query)->getRow();
    }

    /**
     * @param int $wordPressUserId
     * @param string $ticket_id
     * @param string $defaultAttestation
     * @return bool
     */
    public function linkTicketUnsUserIdWithWordPressRegisteredUser($wordPressUserId, $ticket_id, $defaultAttestation){
        $ticket = $this->getTicketById($ticket_id);
        if(!empty($ticket) && !empty($ticket['uns_user_id'])){
            $uns_user_id = $ticket['uns_user_id'];
            $this->database->query(
                'INSERT INTO '.$this->generateTableName(self::TABLE_USERS)
                .'(`wp_user_id`, `uns_user_id`,`attestation_type`) VALUES'
                .'('
                    .(int) $wordPressUserId.','
                    .'"'.$this->database->sanitize($uns_user_id).'",'
                    .' "'.$this->database->sanitize($defaultAttestation).'"'
                .');'
            )->execute();
            return true;
        }

        return false;
    }


    /**
     * SRP
     */
    public function createSRPTable()
    {
        $query = "CREATE TABLE IF NOT EXISTS `" . $this->generateTableName(self::TABLE_SRP) . "` (
            `id` BIGINT(20) NOT NULL AUTO_INCREMENT ,
            `user_id` BIGINT(20) DEFAULT 0,
            `s` TEXT NOT NULL,
            `v` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
             PRIMARY KEY (`id`)
        ) ENGINE = InnoDB;";
        $this->database->query($query)->execute();
    }

    /**
     * @param int $userId
     * @return array|null
     */
    public function getSrpCredentials($userId){
        $query = 'SELECT * FROM '.$this->generateTableName(self::TABLE_SRP)
            .' WHERE user_id = '. (int) $userId . ' LIMIT 1';

        $row = $this->database->query($query)->getRow();
        if(!isset($row['s']) || !isset($row['v'])){
            return null;
        }
        return [
            's' => base64_decode($row['s']),
            'v' => base64_decode($row['v'])
        ];
    }

    public function insertUserCredentials($userId, $s, $v){
        $s = base64_encode($s);
        $v = base64_encode($v);
        $query = 'INSERT INTO '.$this->generateTableName(self::TABLE_SRP)
            . " (user_id, s, v) VALUES ("
            . "'" . (int) $userId . "', "
            . "'" . $this->database->sanitize($s) . "', "
            . "'" . $this->database->sanitize($v) . "'"
            .");";
        $this->database->query($query)->execute();
    }

    public function updateUserCredentials($userId, $s, $v){
        $s = base64_encode($s);
        $v = base64_encode($v);
        $query = 'UPDATE '.$this->generateTableName(self::TABLE_SRP)
            . " SET "
            . "s = '" . $this->database->sanitize($s) . "', "
            . "v = '" . $this->database->sanitize($v) . "'"
            ." WHERE user_id = ". (int) $userId . ' LIMIT 1';

        $this->database->query($query)->execute();
    }
}

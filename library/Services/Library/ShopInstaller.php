<?php
/**
 * This file is part of OXID eSales Testing Library.
 *
 * OXID eSales Testing Library is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID eSales Testing Library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID eSales Testing Library. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2014
 */

if (!defined('SHOP_PATH')) {
    define('SHOP_PATH', __DIR__ . '/../../');
}

include_once __DIR__ . '/DbHandler.php';

/**
 * Class for shop installation.
 */
class ShopInstaller
{
    /** @var resource  */
    private $db = null;

    /** @var string Shop setup directory path */
    private $setupDirectory = null;

    /** @var DbHandler */
    private $dbHandler;

    /**
     * Includes configuration files.
     */
    public function __construct()
    {
        if (file_exists(SHOP_PATH ."_version_define.php")) {
            include SHOP_PATH ."_version_define.php";
        } else if (!defined('OXID_VERSION_SUFIX')) {
            define('OXID_VERSION_SUFIX', '');
        }

        $this->dbHandler = new DbHandler();

        include SHOP_PATH . "config.inc.php";
        include SHOP_PATH . "core/oxconfk.php";
    }

    /**
     * Sets shop setup directory.
     *
     * @param string $sSetupPath Path to setup files to use instead of shop ones.
     */
    public function setSetupDirectory($sSetupPath)
    {
        $this->setupDirectory = $sSetupPath;
    }

    /**
     * Returns shop setup directory.
     *
     * @return string
     */
    public function getSetupDirectory()
    {
        if ($this->setupDirectory === null) {
            $this->setupDirectory = SHOP_PATH . '/setup';
        }

        return $this->setupDirectory;
    }

    /**
     * Deletes browser cookies.
     *
     * @return array
     */
    public function deleteCookies()
    {
        $aDeletedCookies = array();
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $aCookies = explode(';', $_SERVER['HTTP_COOKIE']);
            foreach ($aCookies as $sCookie) {
                $sRawCookie = explode('=', $sCookie);
                setcookie(trim($sRawCookie[0]), '', time() - 10000, '/');
                $aDeletedCookies[] = $sRawCookie[0];
            }
        }
        return $aDeletedCookies;
    }

    /**
     * Clears temp directory.
     */
    public function clearTemp()
    {
        $this->delTree($this->sCompileDir, false);
    }

    /**
     * Sets up database.
     */
    public function setupDatabase()
    {
        if ($this->getCharsetMode() == 'utf8') {
            $this->query("alter schema character set utf8 collate utf8_general_ci");
            $this->query("set names 'utf8'");
            $this->query("set character_set_database=utf8");
            $this->query("set character set latin1");//mysql_query("set character set utf8",$oDB);
            $this->query("set character_set_connection = utf8");
            $this->query("set character_set_results = utf8");
            $this->query("set character_set_server = utf8");
        } else {
            $this->query("alter schema character set latin1 collate latin1_general_ci");
            $this->query("set character set latin1");
        }

        $this->query('drop database `' . $this->dbName . '`');
        $this->query('create database `' . $this->dbName . '` collate ' . $this->getCharsetMode() . '_general_ci');

        $sSetupPath = $this->getSetupDirectory();
        $this->importFileToDatabase($sSetupPath . '/sql' . OXID_VERSION_SUFIX . '/' . 'database.sql', false);
    }

    /**
     * Inserts demo data to shop.
     */
    public function insertDemoData()
    {
        $sSetupPath = $this->getSetupDirectory();
        $this->importFileToDatabase($sSetupPath . '/sql' . OXID_VERSION_SUFIX . '/' . 'demodata.sql', false);
    }

    /**
     * Convert shop to international.
     */
    public function convertToInternational()
    {
        $sSetupPath = $this->getSetupDirectory();
        $this->importFileToDatabase($sSetupPath . '/sql' . OXID_VERSION_SUFIX . '/' . 'en.sql', false);
    }

    /**
     * Inserts missing configuration parameters
     */
    public function setConfigurationParameters()
    {
        $sShopId = $this->getShopId();

        $this->query("delete from oxconfig where oxvarname in ('iSetUtfMode','blLoadDynContents','sShopCountry');");
        $this->query(
            "insert into oxconfig (oxid, oxshopid, oxvarname, oxvartype, oxvarvalue) values " .
            "('config1', '{$sShopId}', 'iSetUtfMode',       'str',  ENCODE('0', '{$this->sConfigKey}') )," .
            "('config2', '{$sShopId}', 'blLoadDynContents', 'bool', ENCODE('1', '{$this->sConfigKey}') )," .
            "('config3', '{$sShopId}', 'sShopCountry',      'str',  ENCODE('de','{$this->sConfigKey}') )"
        );
    }

    /**
     * Adds serial number to shop.
     *
     * @param string $serialNumber
     */
    public function setSerialNumber($serialNumber = null)
    {
        if (file_exists(SHOP_PATH . "core/oxserial.php")) {
            include_once SHOP_PATH . "core/oxserial.php";
        }

        if (class_exists('oxSerial')) {
            if (!$serialNumber) {
                $serialNumber = $this->getDefaultSerial();
            }

            $shopId = $this->getShopId();

            $serial = new oxSerial();
            $serial->setEd($this->getShopEdition() == 'EE' ? 2 : 1);

            $serial->isValidSerial($serialNumber);

            $maxDays = $serial->getMaxDays($serialNumber);
            $maxArticles = $serial->getMaxArticles($serialNumber);
            $maxShops = $serial->getMaxShops($serialNumber);

            $this->query("update oxshops set oxserial = '{$serialNumber}'");
            $this->query("delete from oxconfig where oxvarname in ('aSerials','sTagList','IMD','IMA','IMS')");
            $this->query(
                "insert into oxconfig (oxid, oxshopid, oxvarname, oxvartype, oxvarvalue) values " .
                "('serial1', '{$shopId}', 'aSerials', 'arr', ENCODE('" . serialize(array($serialNumber)) . "','{$this->sConfigKey}') )," .
                "('serial2', '{$shopId}', 'sTagList', 'str', ENCODE('" . time() . "','{$this->sConfigKey}') )," .
                "('serial3', '{$shopId}', 'IMD',      'str', ENCODE('" . $maxDays . "','{$this->sConfigKey}') )," .
                "('serial4', '{$shopId}', 'IMA',      'str', ENCODE('" . $maxArticles . "','{$this->sConfigKey}') )," .
                "('serial5', '{$shopId}', 'IMS',      'str', ENCODE('" . $maxShops . "','{$this->sConfigKey}') )"
            );
        }
    }

    /**
     * Converts shop to utf8.
     */
    public function convertToUtf()
    {
        $oDB = $this->getDb();

        $rs = $this->query(
            "SELECT oxvarname, oxvartype, DECODE( oxvarvalue, '{$this->sConfigKey}') AS oxvarvalue
                       FROM oxconfig
                       WHERE oxvartype IN ('str', 'arr', 'aarr')
                       #AND oxvarname != 'aCurrencies'
                       "
        );

        $aConverted = array();
        while ($aRow = mysql_fetch_assoc($rs)) {
            if ($aRow['oxvartype'] == 'arr' || $aRow['oxvartype'] == 'aarr') {
                $aRow['oxvarvalue'] = unserialize($aRow['oxvarvalue']);
            }
            $aRow['oxvarvalue'] = $this->stringToUtf($aRow['oxvarvalue']);
            $aConverted[] = $aRow;
        }

        foreach ($aConverted as $aConfigParam) {
            $sConfigName = $aConfigParam['oxvarname'];
            $sConfigValue = $aConfigParam['oxvarvalue'];
            if (is_array($sConfigValue)) {
                $sConfigValue = mysql_real_escape_string(serialize($sConfigValue), $oDB);
            } elseif (is_string($sConfigValue)) {
                $sConfigValue = mysql_real_escape_string($sConfigValue, $oDB);
            }

            $this->query("update oxconfig set oxvarvalue = ENCODE( '{$sConfigValue}','{$this->sConfigKey}') where oxvarname = '{$sConfigName}';");
        }

        // Change currencies value to same as after 4.6 setup because previous encoding break it.
        if ($this->getShopEdition() == 'EE') {
            $query = "REPLACE INTO `oxconfig` (`OXID`, `OXSHOPID`, `OXMODULE`, `OXVARNAME`, `OXVARTYPE`, `OXVARVALUE`) VALUES
                ('3c4f033dfb8fd4fe692715dda19ecd28', 1, '', 'aCurrencies', 'arr', 0x4dbace2972e14bf2cbd3a9a45157004422e928891572b281961cdebd1e0bbafe8b2444b15f2c7b1cfcbe6e5982d87434c3b19629dacd7728776b54d7caeace68b4b05c6ddeff2df9ff89b467b14df4dcc966c504477a9eaeadd5bdfa5195a97f46768ba236d658379ae6d371bfd53acd9902de08a1fd1eeab18779b191f3e31c258a87b58b9778f5636de2fab154fc0a51a2ecc3a4867db070f85852217e9d5e9aa60507);";
        } else {
            $query = "REPLACE INTO `oxconfig` (`OXID`, `OXSHOPID`, `OXMODULE`, `OXVARNAME`, `OXVARTYPE`, `OXVARVALUE`) VALUES
                ('3c4f033dfb8fd4fe692715dda19ecd28', 'oxbaseshop', '', 'aCurrencies', 'arr', 0x4dbace2972e14bf2cbd3a9a45157004422e928891572b281961cdebd1e0bbafe8b2444b15f2c7b1cfcbe6e5982d87434c3b19629dacd7728776b54d7caeace68b4b05c6ddeff2df9ff89b467b14df4dcc966c504477a9eaeadd5bdfa5195a97f46768ba236d658379ae6d371bfd53acd9902de08a1fd1eeab18779b191f3e31c258a87b58b9778f5636de2fab154fc0a51a2ecc3a4867db070f85852217e9d5e9aa60507);";
        }
        $this->query($query);
    }

    /**
     * Turns varnish on.
     */
    public function turnVarnishOn()
    {
        $this->query("DELETE from oxconfig WHERE oxshopid = 1 AND oxvarname in ('iLayoutCacheLifeTime', 'blReverseProxyActive');");
        $this->query(
            "INSERT INTO oxconfig (oxid, oxshopid, oxvarname, oxvartype, oxvarvalue) VALUES
              ('35863f223f91930177693956aafe69e6', 1, 'iLayoutCacheLifeTime', 'str', 0xB00FB55D),
              ('dbcfca66eed01fd43963443d35b109e0', 1, 'blReverseProxyActive',  'bool', 0x07);"
        );
    }

    /**
     * Imports file data to database.
     *
     * @param string $file           Path to file.
     * @param bool   $setCharsetMode Whether to set default charset mode when doing import.
     */
    public function importFileToDatabase($file, $setCharsetMode = true)
    {
        $this->getDbHandler()->import($file, $setCharsetMode);
    }

    /**
     * @return DbHandler
     */
    protected function getDbHandler()
    {
        return $this->dbHandler;
    }

    /**
     * Returns default demo serial number for testing.
     */
    protected function getDefaultSerial()
    {
        include_once SHOP_PATH . "setup/oxsetup.php";

        $setup = new oxSetup();
        return $setup->getDefaultSerial();
    }

    /**
     * Returns shop id.
     *
     * @return string
     */
    private function getShopId()
    {
        return $this->getShopEdition() == 'EE' ? '1' : 'oxbaseshop';
    }

    /**
     * Returns shop edition.
     *
     * @return int
     */
    private function getShopEdition()
    {
        if (defined('OXID_VERSION_EE')) {
            $shopEdition = OXID_VERSION_EE ? 'EE' : '';
            $shopEdition = OXID_VERSION_PE_PE ? 'PE' : $shopEdition;
            $shopEdition = OXID_VERSION_PE_CE ? 'CE' : $shopEdition;
        } else {
            include_once SHOP_PATH . 'core/oxsupercfg.php';
            include_once SHOP_PATH . 'core/oxconfig.php';
            $config = new oxConfig();
            $shopEdition = $config->getEdition();
        }

        return $shopEdition;
    }

    /**
     * Returns charset mode
     *
     * @return string
     */
    private function getCharsetMode()
    {
        return $this->iUtfMode ? 'utf8' : 'latin1';
    }

    /**
     * Returns database resource
     *
     * @return resource
     */
    private function getDb()
    {
        if (is_null($this->db)) {
            $this->db = mysql_connect($this->dbHost, $this->dbUser, $this->dbPwd);
        }

        return $this->db;
    }

    /**
     * Executes query on database.
     *
     * @param string $sql Sql query to execute.
     *
     * @return resource
     */
    private function query($sql)
    {
        $oDB = $this->getDb();
        mysql_select_db($this->dbName, $oDB);

        return mysql_query($sql, $oDB);
    }

    /**
     * Deletes directory tree.
     *
     * @param string $dir       Path to directory
     * @param bool   $rmBaseDir Whether to remove base directory
     */
    private function delTree($dir, $rmBaseDir = false)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file", true) : @unlink("$dir/$file");
        }
        if ($rmBaseDir) {
            @rmdir($dir);
        }
    }

    /**
     * Converts input string to utf8.
     *
     * @param string $input String for conversion.
     *
     * @return array|string
     */
    private function stringToUtf($input)
    {
        $output = array();
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $output[$this->stringToUtf($key)] = $this->stringToUtf($value);
            }
        } elseif (is_string($input)) {
            return iconv('iso-8859-15', 'utf-8', $input);
        } else {
            return $input;
        }
        return $output;
    }
}

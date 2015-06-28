<?php
/**
 * /classes/DomainMOD/DwRecords.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
?>
<?php
namespace DomainMOD;

class DwRecords
{

    public function createTable($connection)
    {

        $sql_records = "CREATE TABLE IF NOT EXISTS dw_dns_records (
                            id INT(10) NOT NULL AUTO_INCREMENT,
                            server_id INT(10) NOT NULL,
                            dns_zone_id INT(10) NOT NULL,
                            domain VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            zonefile VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                            new_order INT(10) NOT NULL,
                            mname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            rname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            `serial` INT(20) NOT NULL,
                            refresh INT(10) NOT NULL,
                            retry INT(10) NOT NULL,
                            expire VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            minimum INT(10) NOT NULL,
                            nsdname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            ttl INT(10) NOT NULL,
                            class VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            type VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            address VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            cname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            `exchange` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            preference INT(10) NOT NULL,
                            txtdata VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            line INT(10) NOT NULL,
                            nlines INT(10) NOT NULL,
                            raw LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                            insert_time DATETIME NOT NULL,
                            PRIMARY KEY  (id)
                        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1";
        mysqli_query($connection, $sql_records);

        return true;

    }

    public function apiGetRecords($protocol, $host, $port, $username, $hash, $domain)
    {

        $api_type = "/xml-api/dumpzone?domain=" . $domain . "";

        $build = new DwBuild();
        $api_results = $build->apiCall($api_type, $protocol, $host, $port, $username, $hash);

        return $api_results;

    }

    public function insertRecords($connection, $api_results, $server_id, $zone_id, $domain)
    {

        if ($api_results !== false) {

            $xml = simplexml_load_string($api_results);

            $time = new DwBuild();

            foreach ($xml->result->record as $hit) {

                $sql = "INSERT INTO dw_dns_records
                        (server_id, dns_zone_id, domain, mname, rname, `serial`, refresh, retry, expire,
                         minimum, nsdname, `name`, ttl, class, type, address, cname, `exchange`, preference,
                         txtdata, line, nlines, raw, insert_time)
                        VALUES
                        ('" . $server_id . "', '" . $zone_id . "', '" . $domain . "', '" .
                    $hit->mname . "', '" . $hit->rname . "', '" . $hit->serial . "', '" . $hit->refresh . "', '" .
                    $hit->retry . "', '" . $hit->expire . "', '" . $hit->minimum . "', '" . $hit->nsdname . "', '"
                    . $hit->name . "', '" . $hit->ttl . "', '" . $hit->class . "', '" . $hit->type . "', '" .
                    $hit->address . "', '" . $hit->cname . "', '" . $hit->exchange . "', '" . $hit->preference .
                    "', '" . $hit->txtdata . "', '" . $hit->Line . "', '" . $hit->Lines . "', '" . $hit->raw .
                    "', '" . $time->time() . "')";
                mysqli_query($connection, $sql);

            }

        }

        return true;

    }

    public function cleanupRecords($connection)
    {

        $sql = "DELETE FROM dw_dns_records
                WHERE type = ':RAW'
                  AND raw = ''";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET type = 'COMMENT'
                WHERE type = ':RAW'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET type = 'ZONE TTL'
                WHERE type = '\$TTL'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET nlines = '1'
                WHERE nlines = '0'";
        mysqli_query($connection, $sql);

        $sql = "SELECT domain, zonefile
                FROM dw_dns_zones";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql_update = "UPDATE dw_dns_records
                           SET zonefile = '" . $row->zonefile . "'
                           WHERE domain = '" . $row->domain . "'";
            mysqli_query($connection, $sql_update);

        }

        return true;

    }

    public function reorderRecords($connection)
    {

        $type_order = array();
        $count = 0;
        $new_order = 1;
        $type_order[$count++] = 'COMMENT';
        $type_order[$count++] = 'ZONE TTL';
        $type_order[$count++] = 'SOA';
        $type_order[$count++] = 'NS';
        $type_order[$count++] = 'MX';
        $type_order[$count++] = 'A';
        $type_order[$count++] = 'CNAME';
        $type_order[$count++] = 'TXT';
        $type_order[$count++] = 'SRV';

        foreach ($type_order as $key) {

            $sql = "UPDATE dw_dns_records
                    SET new_order = '" . $new_order++ . "'
                    WHERE type = '" . $key . "'";
            mysqli_query($connection, $sql);

        }

        return true;

    }

    public function getTotalDwRecords($connection)
    {

        $total_dw_records = '';

        $sql_records = "SELECT count(*) AS total_dw_records
                      FROM `dw_dns_records`";
        $result_records = mysqli_query($connection, $sql_records);

        while ($row_records = mysqli_fetch_object($result_records)) {

            $total_dw_records = $row_records->total_dw_records;

        }

        return $total_dw_records;

    }

}
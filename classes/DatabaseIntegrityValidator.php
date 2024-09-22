<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace PrestaShop\Module\AutoUpgrade;

class DatabaseIntegrityValidator
{

    const DB_STRUCTURE_FILE = 'db_structure.sql';


    public function getStructureFile()
    {
        $file_path = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'install-dev' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . self::DB_STRUCTURE_FILE;
        $sql = file_get_contents($file_path);
        if ($sql === false) {
            die("Erreur lors de la lecture du fichier SQL.");
        }

        return $sql;
    }

    function extractTableSchemas(): array
    {
        $sql = $this->getStructureFile();

        preg_match_all('/CREATE TABLE\s+(IF NOT EXISTS\s+)?`?(\w+)`?\s*\((.*?)\)\s*(ENGINE|CHARSET|COLLATE|DEFAULT|AUTO_INCREMENT|COMMENT|ROW_FORMAT|;)/si', $sql, $matches, PREG_SET_ORDER);

        $schemas = [];

        foreach ($matches as $match) {
            $tableName = $match[2];
            $columnsBlock = $match[3];

            $columns = preg_split('/,\s*(?![^()]*\))/', trim($columnsBlock));
            $columnsInfo = [];

            foreach ($columns as $column) {
                $column = trim($column);

                if (preg_match('/^(PRIMARY KEY|UNIQUE|KEY|INDEX|CONSTRAINT|FOREIGN KEY)/i', $column)) {
                    continue;
                }

                if (preg_match('/`?(\w+)`?\s+([a-zA-Z]+(\([^)]*\))?(\s*(UNSIGNED|SIGNED)?)?)(.*)/i', $column, $colMatch)) {
                    $colName = $colMatch[1];
                    $colType = $colMatch[2];
                    $colDetails = $colMatch[6];

                    $isNullable = stripos($colDetails, 'NOT NULL') === false;
                    $isAutoIncrement = stripos($colDetails, 'AUTO_INCREMENT') !== false;
                    $defaultValue = null;

                    if (preg_match('/DEFAULT\s+([^\s,]+)/i', $colDetails, $defaultMatch)) {
                        $defaultValue = trim($defaultMatch[1], " '");
                    }

                    $columnsInfo[] = [
                        'name' => $colName,
                        'type' => $colType,
                        'nullable' => $isNullable,
                        'auto_increment' => $isAutoIncrement,
                        'default' => $defaultValue,
                    ];
                }
            }

            $schemas[$tableName] = $columnsInfo;
        }

        return $schemas;
    }
}
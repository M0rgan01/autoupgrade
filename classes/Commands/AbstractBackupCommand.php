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

namespace PrestaShop\Module\AutoUpgrade\Commands;

use DateTime;
use Exception;
use PrestaShop\Module\AutoUpgrade\Backup\BackupFinder;
use PrestaShop\Module\AutoUpgrade\Exceptions\BackupException;
use PrestaShop\Module\AutoUpgrade\UpgradeContainer;

abstract class AbstractBackupCommand extends AbstractCommand
{
    /**
     * @throws Exception
     *
     * @return string[]
     */
    protected function getBackups(): array
    {
        $backupPath = $this->upgradeContainer->getProperty(UpgradeContainer::BACKUP_PATH);

        return (new BackupFinder($backupPath))->getAvailableBackups();
    }

    /**
     * @param string $backupName
     *
     * @return array{timestamp: int, datetime: string, version:string, filename: string}
     *
     * @throws BackupException
     */
    protected function parseBackupMetadata(string $backupName): array
    {
        $pattern = '/V(\d+(\.\d+){1,3})_([0-9]{8})-([0-9]{6})/';
        if (preg_match($pattern, $backupName, $matches)) {
            $version = $matches[1];
            $datePart = $matches[3];
            $timePart = $matches[4];

            $dateTime = DateTime::createFromFormat('Ymd His', $datePart . ' ' . $timePart);
            $timestamp = $dateTime->getTimestamp();

            return
                [
                    'timestamp' => $timestamp,
                    'datetime' => $this->getFormattedDatetime($timestamp),
                    'version' => $version,
                    'filename' => $backupName,
                ];
        }

        throw new BackupException('An error occurred while formatting the backup name.');
    }

    private function getFormattedDatetime(int $timestamp): string
    {
        setlocale(LC_TIME, '');

        return strftime('%x %X', $timestamp);
    }
}

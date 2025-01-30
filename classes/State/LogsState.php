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

namespace PrestaShop\Module\AutoUpgrade\State;

use PrestaShop\Module\AutoUpgrade\Parameters\UpgradeFileNames;

class LogsState extends AbstractState
{
    /** @var string|null */
    protected $activeBackupLogFile;

    /** @var string|null */
    protected $activeRestoreLogFile;

    /** @var string|null */
    protected $activeUpdateLogFile;

    /** @var string|null */
    protected $timeZone;

    protected function getFileNameForPersistentStorage(): string
    {
        return UpgradeFileNames::STATE_LOGS_FILENAME;
    }

    public function getActiveBackupLogFile(): ?string
    {
        return $this->activeBackupLogFile;
    }

    public function setActiveBackupLogFromDateTime(string $datetime): self
    {
        $this->activeBackupLogFile = $datetime . '-backup.txt';
        $this->save();

        return $this;
    }

    public function getActiveRestoreLogFile(): ?string
    {
        return $this->activeRestoreLogFile;
    }

    public function setActiveRestoreLogFromDateTime(string $datetime): self
    {
        $this->activeRestoreLogFile = $datetime . '-restore.txt';
        $this->save();

        return $this;
    }

    public function getActiveUpdateLogFile(): ?string
    {
        return $this->activeUpdateLogFile;
    }

    public function setActiveUpdateLogFromDateTime(string $datetime): self
    {
        $this->activeUpdateLogFile = $datetime . '-update.txt';
        $this->save();

        return $this;
    }

    public function getTimeZone(): ?string
    {
        return $this->timeZone;
    }

    public function setTimeZone(string $timeZone): self
    {
        $this->timeZone = $timeZone;
        $this->save();

        return $this;
    }
}

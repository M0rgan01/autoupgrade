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

namespace PrestaShop\Module\AutoUpgrade\Controller;

use Exception;
use PrestaShop\Module\AutoUpgrade\AjaxResponseBuilder;
use PrestaShop\Module\AutoUpgrade\Parameters\UpgradeConfiguration;
use PrestaShop\Module\AutoUpgrade\Parameters\UpgradeFileNames;
use PrestaShop\Module\AutoUpgrade\Router\Routes;
use PrestaShop\Module\AutoUpgrade\Services\DistributionApiService;
use PrestaShop\Module\AutoUpgrade\Services\PhpVersionResolverService;
use PrestaShop\Module\AutoUpgrade\Twig\PageSelectors;
use PrestaShop\Module\AutoUpgrade\Twig\UpdateSteps;
use PrestaShop\Module\AutoUpgrade\UpgradeContainer;
use PrestaShop\Module\AutoUpgrade\Upgrader;
use PrestaShop\Module\AutoUpgrade\UpgradeSelfCheck;
use PrestaShop\Module\AutoUpgrade\VersionUtils;
use Symfony\Component\HttpFoundation\JsonResponse;

class UpdatePageVersionChoiceController extends AbstractPageController
{
    const CURRENT_STEP = UpdateSteps::STEP_VERSION_CHOICE;
    const FORM_NAME = 'version_choice';
    const FORM_FIELDS = [
        UpgradeConfiguration::CHANNEL => UpgradeConfiguration::CHANNEL,
        UpgradeConfiguration::ARCHIVE_ZIP => UpgradeConfiguration::ARCHIVE_ZIP,
        UpgradeConfiguration::ARCHIVE_XML => UpgradeConfiguration::ARCHIVE_XML,
    ];
    const FORM_OPTIONS = [
        'online_value' => Upgrader::CHANNEL_ONLINE,
        'local_value' => Upgrader::CHANNEL_LOCAL,
    ];

    protected function getPageTemplate(): string
    {
        return 'update';
    }

    protected function getStepTemplate(): string
    {
        return self::CURRENT_STEP;
    }

    protected function displayRouteInUrl(): ?string
    {
        return Routes::UPDATE_PAGE_VERSION_CHOICE;
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    protected function getParams(): array
    {
        $updateSteps = new UpdateSteps($this->upgradeContainer->getTranslator());
        $isNewerVersionAvailableOnline = $this->upgradeContainer->getUpgrader()->isNewerVersionAvailableOnline();
        $onlineDestination = $this->upgradeContainer->getUpgrader()->getOnlineDestinationRelease();

        if ($isNewerVersionAvailableOnline) {
            $updateType = VersionUtils::getUpdateType($this->getPsVersion(), $onlineDestination->getVersion());
            $releaseNote = $this->upgradeContainer->getUpgrader()->getOnlineDestinationRelease()->getReleaseNoteUrl();
        } else {
            $updateType = null;
            $releaseNote = null;
        }

        switch ($updateType) {
            case 'major':
                $updateLabel = $this->upgradeContainer->getTranslator()->trans('Major version');
                break;
            case 'minor':
                $updateLabel = $this->upgradeContainer->getTranslator()->trans('Minor version');
                break;
            case 'patch':
                $updateLabel = $this->upgradeContainer->getTranslator()->trans('Patch version');
                break;
            default:
                $updateLabel = null;
        }
        $archiveRepository = $this->upgradeContainer->getLocalArchiveRepository();

        $upgradeConfiguration = $this->upgradeContainer->getUpgradeConfiguration();
        $currentChannel = $upgradeConfiguration->getChannel();

        $params = array_merge(
            $updateSteps->getStepParams($this::CURRENT_STEP),
            [
                'up_to_date' => !$isNewerVersionAvailableOnline,
                'no_local_archive' => !$this->upgradeContainer->getLocalArchiveRepository()->hasLocalArchive(),
                'assets_base_path' => $this->upgradeContainer->getAssetsEnvironment()->getAssetsBaseUrl($this->request),
                'current_prestashop_version' => $this->getPsVersion(),
                'current_php_version' => VersionUtils::getHumanReadableVersionOf(PHP_VERSION_ID),
                'local_archives' => [
                    'zip' => $archiveRepository->getZipLocalArchive(),
                    'xml' => $archiveRepository->getXmlLocalArchive(),
                ],
                'next_release' => [
                    'version' => $onlineDestination ? $onlineDestination->getVersion() : null,
                    'badge_label' => $updateLabel,
                    'badge_status' => $updateType,
                    'release_note' => $releaseNote,
                ],
                'form_version_choice_name' => self::FORM_NAME,
                'form_route_to_save' => Routes::UPDATE_STEP_VERSION_CHOICE_SAVE_FORM,
                'form_route_to_submit' => Routes::UPDATE_STEP_VERSION_CHOICE_SUBMIT_FORM,
                'form_fields' => self::FORM_FIELDS,
                'form_options' => self::FORM_OPTIONS,
                'current_values' => [
                    self::FORM_FIELDS['channel'] => $currentChannel,
                    self::FORM_FIELDS['archive_zip'] => $upgradeConfiguration->getLocalChannelZip(),
                    self::FORM_FIELDS['archive_xml'] => $upgradeConfiguration->getLocalChannelXml(),
                ],
            ]
        );

        if ($currentChannel === self::FORM_OPTIONS['online_value'] ||
            (
                $currentChannel === self::FORM_OPTIONS['local_value']
                && in_array($upgradeConfiguration->getLocalChannelZip(), $archiveRepository->getZipLocalArchive())
                && in_array($upgradeConfiguration->getLocalChannelXml(), $archiveRepository->getXmlLocalArchive())
            )
        ) {
            $params[$currentChannel . '_requirements'] = $this->getRequirements();
        }

        return $params;
    }

    /**
     * @throws Exception
     */
    private function getRequirements(): array
    {
        $this->upgradeContainer->initPrestaShopCore();

        $distributionApiService = new DistributionApiService();
        $phpVersionResolverService = new PhpVersionResolverService(
            $distributionApiService,
            $this->upgradeContainer->getFileLoader(),
            $this->upgradeContainer->getState()->getCurrentVersion()
        );

        $upgradeSelfCheck = new UpgradeSelfCheck(
            $this->upgradeContainer->getUpgrader(),
            $this->upgradeContainer->getState(),
            $this->upgradeContainer->getUpgradeConfiguration(),
            $this->upgradeContainer->getPrestaShopConfiguration(),
            $this->upgradeContainer->getTranslator(),
            $phpVersionResolverService,
            $this->upgradeContainer->getChecksumCompare(),
            _PS_ROOT_DIR_,
            _PS_ADMIN_DIR_,
            $this->upgradeContainer->getProperty(UpgradeContainer::WORKSPACE_PATH)
        );

        $warnings = $upgradeSelfCheck->getWarnings();
        foreach ($warnings as $warningKey => $warningValue) {
            $warnings[$warningKey] = $upgradeSelfCheck->getRequirementWording($warningKey, true);
        }

        $errors = $upgradeSelfCheck->getErrors();
        foreach ($errors as $errorKey => $errorValue) {
            $errors[$errorKey] = $upgradeSelfCheck->getRequirementWording($errorKey, true);
        }

        return [
            'requirements_ok' => empty($errors),
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * @throws Exception
     */
    public function save(): JsonResponse
    {
        $channel = $this->request->get(self::FORM_FIELDS['channel']);
        $isLocal = $channel === self::FORM_OPTIONS['local_value'];

        $requestConfig = $this->request->request->all();

        $this->upgradeContainer->initPrestaShopCore();

        $errors = $this->upgradeContainer->getConfigurationValidator()->validate($requestConfig);

        if ($isLocal && empty($errors)) {
            $errors = $this->upgradeContainer->getLocalChannelConfigurationValidator()->validate($requestConfig);
        }

        if (empty($errors)) {
            if ($isLocal) {
                $file = $requestConfig[UpgradeConfiguration::ARCHIVE_ZIP];
                $fullFilePath = $this->upgradeContainer->getProperty(UpgradeContainer::DOWNLOAD_PATH) . DIRECTORY_SEPARATOR . $file;
                $requestConfig['archive_version_num'] = $this->upgradeContainer->getPrestashopVersionService()->extractPrestashopVersionFromZip($fullFilePath);
            }

            $config = $this->upgradeContainer->getUpgradeConfiguration();
            $config->merge($requestConfig);

            $this->upgradeContainer->getUpgradeConfigurationStorage()->save($config, UpgradeFileNames::CONFIG_FILENAME);
        } else {
            $errors = array_column(
                array_map(function ($error) {
                    return [
                        'key' => $error['target'] ?? 'global',
                        'value' => $error['message'],
                    ];
                }, $errors),
                'value',
                'key'
            );
        }

        $params = array_merge(
            $this->getParams(),
            [
                'current_values' => $requestConfig,
                'errors' => $errors,
            ]
        );

        if ($isLocal) {
            return AjaxResponseBuilder::hydrationResponse(PageSelectors::RADIO_CARD_ARCHIVE_PARENT_ID, $this->getTwig()->render(
                '@ModuleAutoUpgrade/components/radio-card-local.html.twig',
                $params
            ));
        }

        return AjaxResponseBuilder::hydrationResponse(PageSelectors::RADIO_CARD_ONLINE_PARENT_ID, $this->getTwig()->render(
            '@ModuleAutoUpgrade/components/radio-card-online.html.twig',
            $params
        ));
    }

    public function submit(): JsonResponse
    {
        /* we dont check again because the button is only accessible if check are ok */
        return AjaxResponseBuilder::nextRouteResponse(Routes::UPDATE_STEP_UPDATE_OPTIONS);
    }
}

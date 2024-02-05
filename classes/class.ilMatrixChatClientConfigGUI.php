<?php

declare(strict_types=1);

/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\DI\Container;
use ILIAS\FileUpload\FileUpload;
use ILIAS\Plugin\Libraries\ControllerHandler\UiUtils;
use ILIAS\Plugin\MatrixChatClient\Api\MatrixApi;
use ILIAS\Plugin\MatrixChatClient\Form\ChatPageDesignerForm;
use ILIAS\Plugin\MatrixChatClient\Form\PluginConfigForm;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @ilCtrl_Calls      ilMatrixChatClientConfigGUI: ilPropertyFormGUI
 * @ilCtrl_Calls      ilMatrixChatClientConfigGUI: ilAdministrationGUI
 * @ilCtrl_IsCalledBy ilMatrixChatClientConfigGUI: ilObjComponentSettingsGUI
 */
class ilMatrixChatClientConfigGUI extends ilPluginConfigGUI
{
    public const CMD_SHOW_SETTINGS = "showSettings";
    public const CMD_SAVE_SETTINGS = "saveSettings";

    public const CMD_SHOW_CHAT_PAGE_DESIGNER = "showChatPageDesigner";
    public const CMD_SAVE_CHAT_PAGE_DESIGNER = "saveChatPageDesigner";
    public const TAB_PLUGIN_SETTINGS = "tab_plugin_settings";
    public const TAB_CHAT_PAGE_DESIGNER = "tab_chat_page_designer";

    protected ilObjUser $user;
    protected ilLogger $logger;
    protected FileUpload $upload;
    protected ilMatrixChatClientPlugin $plugin;
    protected ilTabsGUI $tabs;
    protected Container $dic;
    protected ilGlobalPageTemplate $mainTpl;
    protected ilLanguage $lng;
    private ilCtrl $ctrl;
    private UiUtils $uiUtil;
    private MatrixApi $matrixApi;

    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
        $this->lng = $this->dic->language();
        $this->ctrl = $this->dic->ctrl();
        $this->mainTpl = $this->dic->ui()->mainTemplate();
        $this->tabs = $this->dic->tabs();
        $this->upload = $this->dic->upload();
        $this->logger = $this->dic->logger()->root();
        $this->user = $this->dic->user();
        $this->uiUtil = new UiUtils();

        /**
         * @var ilComponentFactory $componentFactory
         */
        $componentFactory = $DIC["component.factory"];
        $this->plugin = $componentFactory->getPlugin("mcc");

        $this->plugin->denyConfigIfPluginNotActive();

        $this->matrixApi = $this->plugin->getMatrixApi();
    }

    public function showSettings(?PluginConfigForm $form = null): void
    {
        $this->injectTabs(self::TAB_PLUGIN_SETTINGS);
        if ($form === null) {
            $form = new PluginConfigForm();

            $form->setValuesByArray(
                $this->plugin->getPluginConfig()->toArray(["matrixAdminPassword", "sharedSecret"]),
                true
            );
        }

        $this->mainTpl->setContent($form->getHTML());
    }

    public function saveSettings(): void
    {
        $form = new PluginConfigForm();

        if (!$form->checkInput()) {
            $this->uiUtil->sendFailure($this->plugin->txt("general.update.failed"));
            $form->setValuesByPost();
            $this->showSettings($form);
            return;
        }

        $form->setValuesByPost();

        $sharedSecretValue = $form->getInput("sharedSecret");

        if (!$sharedSecretValue && !$this->plugin->getPluginConfig()->getSharedSecret()) {
            /** @var ilPasswordInputGUI $sharedSecret */
            $sharedSecret = $form->getItemByPostVar("sharedSecret");
            $sharedSecret->setRequired(true);
            $this->uiUtil->sendFailure($this->lng->txt("form_input_not_valid"), true);
            $sharedSecret->setAlert($this->plugin->txt("config.sharedSecret.empty"));
            $this->showSettings($form);
            return;
        }

        $sharedSecretValue = $sharedSecretValue ?: $this->plugin->getPluginConfig()->getSharedSecret();

        $matrixSpaceName = $form->getInput("matrixSpaceName");
        if ($matrixSpaceName !== $this->plugin->getPluginConfig()->getMatrixSpaceName()) {
            //Create new Matrix Space
            $space = $this->matrixApi->createSpace($matrixSpaceName);
            if (!$space) {
                $this->uiUtil->sendFailure($this->plugin->txt("matrix.space.creation.failure"), true);
                $this->ctrl->redirectByClass(self::class, self::CMD_SHOW_SETTINGS);
            }

            $this->plugin->getPluginConfig()
                ->setMatrixSpaceName($matrixSpaceName)
                ->setMatrixSpaceId($space->getId());
        }

        $this->plugin->getPluginConfig()
            ->setMatrixServerUrl(rtrim($form->getInput("matrixServerUrl"), "/"))
            ->setMatrixAdminUsername($form->getInput("matrixAdminUsername"))
            ->setMatrixRestApiUserUsername($form->getInput("matrixRestApiUserUsername"))
            ->setSharedSecret($sharedSecretValue)
            ->setExternalUserScheme($form->getInput("externalUserScheme"))
            ->setExternalUserOptions($form->getInput("externalUserOptions"))
            ->setLocalUserScheme($form->getInput("localUserScheme"))
            ->setLocalUserOptions($form->getInput("localUserOptions"))
            ->setRoomPrefix($form->getInput("roomPrefix"))
            ->setSupportedObjectTypes($form->getInput("supportedObjectTypes"))
            ->setEnableRoomEncryption((bool) $form->getInput("enableRoomEncryption"))
            ->setModifyParticipantPowerLevel((bool) $form->getInput("modifyParticipantPowerLevel"))
            ->setAdminPowerLevel((int) $form->getInput("adminPowerLevel"))
            ->setTutorPowerLevel((int) $form->getInput("tutorPowerLevel"))
            ->setMemberPowerLevel((int) $form->getInput("memberPowerLevel"));

        $matrixAdminPassword = $form->getInput("matrixAdminPassword");
        if ($matrixAdminPassword !== "") {
            $this->plugin->getPluginConfig()->setMatrixAdminPassword($matrixAdminPassword);
        }

        $matrixRestApiUserPassword = $form->getInput("matrixRestApiUserPassword");
        if ($matrixRestApiUserPassword !== "") {
            $this->plugin->getPluginConfig()->setMatrixRestApiUserPassword($matrixRestApiUserPassword);
        }

        $matrixAdminPasswordRemoveRateLimit = (bool) $form->getInput("matrixAdminPasswordRemoveRateLimit");
        $matrixRestApiUserRemoveRateLimit = (bool) $form->getInput("matrixRestApiUserRemoveRateLimit");

        if ($matrixAdminPasswordRemoveRateLimit) {
            $matrixAdminUser = $this->matrixApi->getAdminUser();
            if ($matrixAdminUser) {
                $this->matrixApi->setOverrideRateLimit($matrixAdminUser, 0, 0);
            }
        }

        if ($matrixRestApiUserRemoveRateLimit) {
            $matrixRestApiUser = $this->matrixApi->getRestApiUser();
            if ($matrixRestApiUser) {
                $this->matrixApi->setOverrideRateLimit($matrixRestApiUser, 0, 0);
            }
        }

        try {
            $this->plugin->getPluginConfig()->save();
            $this->uiUtil->sendSuccess($this->plugin->txt("general.update.success"), true);
        } catch (Exception $e) {
            $this->uiUtil->sendFailure($this->plugin->txt($e->getMessage()), true);
        }
        $this->ctrl->redirectByClass(self::class, self::CMD_SHOW_SETTINGS);
    }


    public function showChatPageDesigner(?ChatPageDesignerForm $form = null): void
    {
        $this->injectTabs(self::TAB_CHAT_PAGE_DESIGNER);

        if ($form === null) {
            $form = new ChatPageDesignerForm();

            $form->setValuesByArray(
                $this->plugin->getPluginConfig()->toArray(["matrixAdminPassword", "sharedSecret"]),
                true
            );
        }
        $this->mainTpl->setContent($form->getHTML());
    }

    public function saveChatPageDesigner(): void
    {
        $form = new ChatPageDesignerForm();

        if (!$form->checkInput()) {
            $this->uiUtil->sendFailure($this->plugin->txt("general.update.failed"));
            $form->setValuesByPost();
            $this->showChatPageDesigner($form);
            return;
        }

        $form->setValuesByPost();

        $this->plugin->getPluginConfig()
            ->setPageDesignerText($form->getInput("pageDesignerText"));

        try {
            $this->plugin->getPluginConfig()->save();
            $this->uiUtil->sendSuccess($this->plugin->txt("general.update.success"), true);
        } catch (Exception $e) {
            $this->uiUtil->sendFailure($this->plugin->txt($e->getMessage()), true);
        }
        $this->ctrl->redirectByClass(self::class, self::CMD_SHOW_CHAT_PAGE_DESIGNER);
    }

    public function injectTabs(?string $tabId = null): void
    {
        $this->tabs->addTab(
            self::TAB_PLUGIN_SETTINGS,
            $this->plugin->txt("general.plugin.settings"),
            $this->ctrl->getLinkTargetByClass(self::class, self::CMD_SHOW_SETTINGS)
        );
        $this->tabs->addTab(
            self::TAB_CHAT_PAGE_DESIGNER,
            $this->plugin->txt("config.pageDesignerText.title"),
            $this->ctrl->getLinkTargetByClass(self::class, self::CMD_SHOW_CHAT_PAGE_DESIGNER)
        );

        if ($tabId) {
            $this->tabs->activateTab($tabId);
        }
    }

    public function performCommand(string $cmd): void
    {
        $cmd = $cmd === "configure" ? $this->getDefaultCommand() : $cmd;

        if (method_exists($this, $cmd)) {
            $this->{$cmd}();
        } else {
            $this->uiUtil->sendFailure(sprintf($this->plugin->txt("general.cmd.notFound"), $cmd));
            $this->{$this->getDefaultCommand()}();
        }
    }

    protected function getDefaultCommand(): string
    {
        return self::CMD_SHOW_SETTINGS;
    }
}

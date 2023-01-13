<?php

declare(strict_types=1);
/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *********************************************************************/

namespace ILIAS\Plugin\MatrixChatClient\Form;

use ilPropertyFormGUI;
use ilMatrixChatClientPlugin;
use ilTextInputGUI;
use ilPasswordInputGUI;
use ilMatrixChatClientConfigGUI;
use ilUriInputGUI;
use ilUtil;
use ilNumberInputGUI;

/**
 * Class PluginConfigForm
 *
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class PluginConfigForm extends ilPropertyFormGUI
{
    /**
     * @var ilMatrixChatClientPlugin
     */
    private $plugin;

    public function __construct()
    {
        parent::__construct();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();

        $this->setFormAction($this->ctrl->getFormActionByClass(ilMatrixChatClientConfigGUI::class, "showSettings"));
        $this->setId("{$this->plugin->getId()}_{$this->plugin->getPluginName()}_plugin_config_form");
        $this->setTitle($this->plugin->txt("general.plugin.settings"));

        if ($this->plugin->matrixApi->general->serverReachable()) {
            ilUtil::sendSuccess($this->plugin->txt("matrix.server.reachable"), true);
        } else {
            ilUtil::sendFailure($this->plugin->txt("matrix.server.unreachable"), true);
        }
        if (!$this->plugin->matrixApi->admin->checkAdminUser()) {
            ilUtil::sendFailure($this->plugin->txt("matrix.admin.loginInvalid"), true);
        }

        $matrixServerUrl = new ilUriInputGUI($this->plugin->txt("matrix.server.url"), "matrixServerUrl");
        $matrixServerUrl->setRequired(true);

        $matrixAdminUsername = new ilTextInputGUI(
            $this->plugin->txt("config.plugin.admin.username.title"),
            "matrixAdminUsername"
        );
        $matrixAdminUsername->setRequired(true);
        $matrixAdminUsername->setInfo($this->plugin->txt("config.plugin.admin.username.info"));

        $matrixAdminPassword = new ilPasswordInputGUI(
            $this->plugin->txt("config.plugin.admin.password.title"),
            "matrixAdminPassword"
        );
        $matrixAdminPassword->setInfo($this->plugin->txt("config.plugin.admin.password.info"));
        $matrixAdminPassword->setSkipSyntaxCheck(true);
        $matrixAdminPassword->setRetype(false);

        $chatInitialLoadLimit = new ilNumberInputGUI(
            $this->plugin->txt("config.plugin.loadLimit.initial.title"),
            "chatInitialLoadLimit"
        );
        $chatInitialLoadLimit->setRequired(true);
        $chatInitialLoadLimit->setInfo($this->plugin->txt("config.plugin.loadLimit.initial.info"));

        $chatHistoryLoadLimit = new ilNumberInputGUI(
            $this->plugin->txt("config.plugin.loadLimit.history.title"),
            "chatHistoryLoadLimit"
        );
        $chatHistoryLoadLimit->setRequired(true);
        $chatHistoryLoadLimit->setInfo($this->plugin->txt("config.plugin.loadLimit.history.info"));

        $usernameScheme = new ilTextInputGUI(
            $this->plugin->txt("config.plugin.usernameScheme.title"),
            "usernameScheme"
        );
        $usernameScheme->setRequired(true);

        $usernameScheme->setInfo(sprintf(
            $this->plugin->txt("config.plugin.usernameScheme.info"),
            "- " . implode("<br>- ", array_map(static function ($variable) : string {
                return "<span>{</span>$variable<span>}</span>";
            }, array_keys($this->plugin->getUsernameSchemeVariables())))
        ));

        $this->addItem($matrixServerUrl);
        $this->addItem($matrixAdminUsername);
        $this->addItem($matrixAdminPassword);
        $this->addItem($chatInitialLoadLimit);
        $this->addItem($chatHistoryLoadLimit);
        $this->addItem($usernameScheme);
        $this->addCommandButton("saveSettings", $this->lng->txt("save"));
    }
}

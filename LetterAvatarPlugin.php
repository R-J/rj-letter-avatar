<?php

class LetterAvatarPlugin extends Gdn_Plugin {
    const SVG_DATA_IMAGE_PREFIX = 'data:image/svg+xml;utf8,';

    /** @var string Initial SVG */
    private static $svg = '<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg" version="1.1">'.
        '<circle cx="50" cy="50" r="45" style="fill: %2$s"/>'.
        '<text x="50" y="50"  dy=".3em" style="text-anchor: middle; font: 36px Verdana, Helvetica, Arial, sans-serif; fill: #fff;">%1$s</text>'.
        '</svg>';

    /**
     * Run when plugin is enabled.
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Init sane config values.
     */
    public function structure() {
        Gdn::config()->touch([
            'Plugins.LetterAvatar.SVG' => self::getSvg(),
            'Plugins.LetterAvatar.UseTwoLetters' => true,
            'Plugins.LetterAvatar.UsePalette' => false,
            'Plugins.LetterAvatar.Palette' => 'green,#0022af,#123,red'
        ]);
    }

    /**
     * Getter for the letter avatars SVG.
     *
     * @return string The SVG to render as an avatar.
     */
    public static function getSvg() {
        self::$svg = Gdn::config('Plugins.LetterAvatar.SVG', self::$svg);
        return self::$svg;
    }

    /**
     * Fetch Letter and Color from UserMeta or create them.
     *
     * @param object $user The user for which to create the avatar.
     *
     * @return array Letter and Color.
     */
    public static function getUserInfo($user) {
        // Try to fetch info from UserMeta first.
        $userMetaModel = Gdn::getContainer()->get(UserMetaModel::class);
        $userInfo = $userMetaModel->getUserMeta($user->UserID, 'Plugin.LetterAvatar.%');

        // Try to fetch letter from UserMeta first.
        $letter = $userInfo['Plugin.LetterAvatar.Letter'] ?? false;
        if ($letter == false) {
            $name = trim($user->Name);
            $nameParts = array_values(array_filter(explode(' ', $name)));
            if (count($nameParts) > 1) {
                // Name contains spaces: "John Doe" => "JD".
                $letter = substr($nameParts[0], 0, 1).substr($nameParts[1], 0, 1);
            } elseif (Gdn::config('Plugins.LetterAvatar.UseTwoLetters', true)) {
                // Show two letters instead of one: "John" => "Jo".
                $letter = substr($nameParts[0], 0, 2);
            } else {
                // Simple default: "John" => "J".
                $letter = substr($nameParts[0], 0, 1);
            }
            $userMetaModel->setUserMeta(
                $user->UserID,
                'Plugin.LetterAvatar.Letter',
                $letter
            );
        }

        // Try to fetch color from UserMeta first.
        $color = $userInfo['Plugin.LetterAvatar.Color'] ?? false;
        if ($color == false) {
            if (Gdn::config('Plugins.LetterAvatar.UsePalette', false)) {
                // If a palette should be used, fetch a random entry.
                $palette = explode(',', Gdn::config('Plugins.LetterAvatar.Palette'));
                $colorCount = count($palette);
                $color = $palette[rand(0, $colorCount -1)];
            } else {
                $color = "#".dechex(rand(0, 16777000));
            }
            $userMetaModel->setUserMeta(
                $user->UserID,
                'Plugin.LetterAvatar.Color',
                $color
            );
        }

        return ['Letter' => $letter, 'Color' => $color];
    }

    /**
     * Dashboard settings page.
     *
     * @param SettingsController $sender instance of the calling class.
     *
     * @return void.
     */
    public function settingsController_letterAvatar_create($sender) {
        $sender->permission('Garden.Settings.Manage');
        $sender->setHighlightRoute('settings/plugins');

        $sender->setData('Title', Gdn::translate('Letter Avatar Settings'));

        $configurationModule = new ConfigurationModule($sender);

        $options = [
            'Plugins.LetterAvatar.SVG' => [
                'LabelCode' => 'Custom SVG',
                'Description' => 'You can design the look of your avatars. It must be a valid SVG where %1$s stands for the letter and %2$s is for the color.',
                'Control' => 'TextBox',
                'Options' => ['MultiLine' => true]
            ],
            'Plugins.LetterAvatar.UseTwoLetters' => [
                'LabelCode' => 'Use Two Letters',
                'Control' => 'Toggle',
                'Default' => true
            ],
            'Plugins.LetterAvatar.UsePalette' => [
                'LabelCode' => 'Use Palette',
                'Description' => 'Either use random colors or only colors from a given range',
                'Control' => 'Toggle',
                'Default' => false
            ],
            'Plugins.LetterAvatar.Palette' => [
                'LabelCode' => 'Palette',
                'Description' => 'If "Use Palette" is switched on, only colors from below will be chosen (This doesn\'t affect existing avatars). Colors must be separated by comma, without spaces',
                'Control' => 'TextBox',
                'Options' => ['MultiLine' => true]
            ],
            'Plugins.LetterAvatar.Reset' => [
                'LabelCode' => 'Reset all avatars',
                'Description' => 'Use this switch to reset all letter avatars when settings are saved',
                'Control' => 'Toggle',
                'Default' => false
            ]
        ];

        $configurationModule->initialize($options);

        if (Gdn::request()->isAuthenticatedPostBack()) {
            // Loop through all settings and save arrays directly to config.
            $formValues = $configurationModule->form()->formValues();
            if ($formValues['Plugins.LetterAvatar.Reset']) {
                // reset all avatars
            }
            unset($formValues['Plugins.LetterAvatar.Reset']);
            foreach ($formValues as $key => $value) {
                if (substr($key, 0, 8) !== 'Plugins.' || !is_array($value)) {
                    continue;
                }
                Gdn::config()->saveToConfig($key, array_values($value));
            }
        }
        $configurationModule->renderAll();
    }
}

if (!function_exists('userPhotoDefaultUrl')) {
    /**
     * Calculate the user's default photo url.
     *
     * @param array|object $user The user to examine.
     *
     * @return string The svg data image
     */
    function userPhotoDefaultUrl($user) {
        if (!is_object($user)) {
            $user = (object)$user;
        }

        $userInfo = LetterAvatarPlugin::getUserInfo($user);
        // $letter = LetterAvatarPlugin::getLetter($user);
        // $color = LetterAvatarPlugin::getColor($user);
        $svg = sprintf(
            LetterAvatarPlugin::getSvg(),
            $userInfo['Letter'],
            $userInfo['Color']
        );
        return LetterAvatarPlugin::SVG_DATA_IMAGE_PREFIX.rawurlencode($svg);
    }
}

if (!function_exists('img')) {
    /**
     * Returns an img tag.
     *
     * Difference to Vanillas img() function is that it allows svg data images.
     *
     * @param string $image
     * @param string $attributes
     * @param bool|false $withDomain
     * @return string
     */
    function img($image, $attributes = '', $withDomain = false) {
        if ($attributes != '') {
            $attributes = attribute($attributes);
        }

        if (
            substr($image, 0, 24) != LetterAvatarPlugin::SVG_DATA_IMAGE_PREFIX &&
            !isUrl($image)
        ) {
            $image = smartAsset($image, $withDomain);
        }

        return '<img src="'.htmlspecialchars($image, ENT_QUOTES).'"'.$attributes.' />';
    }
}

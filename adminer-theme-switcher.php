<?php

/**
 * Quickly switch Adminer themes from browser or command line.
 * @author Victor Nogueira <github@victornogueira.io>
 * @link https://github.com/felladrin/adminer-theme-switcher
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class AdminerThemeSwitcher
{
    protected static $themeList;

    protected static $option;

    public static $prompt = 'Type the number of the theme you want to use: ';

    public static function run()
    {
        if (static::isRunningFromCommandLine()) {
            static::printListAvailableThemes();
            static::readOptionFromCommandLine();
            static::switchTheme();
            return;
        }

        if (static::hasNotSelectedAnOptionFromBrowserYet()) {
            static::printListAvailableThemes();
            static::printJavascriptPrompt();
        } else {
            static::readOptionFromBrowser();
            static::switchTheme();
        }
    }

    public static function isRunningFromCommandLine()
    {
        return (php_sapi_name() === 'cli');
    }

    public static function isRunningOnWindows()
    {
        return (PHP_OS == 'WINNT');
    }

    public static function getLineEnding()
    {
        return (static::isRunningFromCommandLine() ? PHP_EOL : '<br/>');
    }

    public static function getThemeList()
    {
        if (!empty(static::$themeList)) {
            return static::$themeList;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: PHP'
                ]
            ],
            'ssl' => [
                "verify_peer" => false,
                "verify_peer_name" => false
            ]
        ]);

        $urlOfThemesDirFromGithubRepo = 'https://api.github.com/repos/vrana/adminer/contents/designs';

        $jsonThemeList = file_get_contents($urlOfThemesDirFromGithubRepo, false, $context);

        static::$themeList = ($jsonThemeList ? json_decode($jsonThemeList) : false);

        return static::$themeList;
    }

    public static function downloadTheme($themeIndex = null)
    {
        if (is_null($themeIndex)) {
            $themeIndex = static::$option;
        }

        $themeName = static::getThemeList()[$themeIndex]->name;
        $urlOfCssFileFromGithubRepo = "https://raw.githubusercontent.com/vrana/adminer/master/designs/{$themeName}/adminer.css";
        $cssContent = file_get_contents($urlOfCssFileFromGithubRepo);
        $filePath = __DIR__ . '/adminer.css';
        $fileExists = file_exists('adminer.css');
        if (file_put_contents($filePath, $cssContent) !== false) {
            if (!$fileExists) {
                chmod($filePath, 0777);
            }

            return true;
        }

        return false;
    }

    public static function printListAvailableThemes()
    {
        echo "List of available Adminer Themes:" . static::getLineEnding() . static::getLineEnding();

        if (static::getThemeList()) {
            foreach (static::getThemeList() as $index => $theme) {
                if ($theme->type !== 'dir') {
                    continue;
                }

                echo "[{$index}] {$theme->name}" . static::getLineEnding();
            }
        }

        echo static::getLineEnding();
    }

    public static function readOptionFromCommandLine()
    {
        if (static::isRunningOnWindows()) {
            echo static::$prompt;
            static::$option = stream_get_line(STDIN, 1024, static::getLineEnding());
        } else {
            static::$option = readline(static::$prompt);
        }

        return static::$option;
    }

    public static function printResultOf($download)
    {
        if ($download) {
            echo 'Theme switched successfully!' . static::getLineEnding();
        } else {
            echo 'Something went wrong with the download. Try again!' . static::getLineEnding();
        }
    }

    public static function printJavascriptPrompt()
    {
        echo '<script>';
        echo 'setTimeout(function() {';
        echo 'var option = prompt("' . static::$prompt . '", "0");';
        echo 'if (option !== null) { window.location.replace(window.location.href.split("?")[0] + "?option=" + option); }';
        echo '}, 1000);';
        echo '</script>';
    }

    public static function readOptionFromBrowser()
    {
        if (isset($_GET['option'])) {
            static::$option = $_GET['option'];
        }

        return static::$option;
    }

    public static function hasNotSelectedAnOptionFromBrowserYet()
    {
        return !isset($_GET['option']);
    }

    public static function hasSelectedAValidOption()
    {
        return is_numeric(static::$option) && static::$option < count(static::getThemeList());
    }

    public static function printInvalidOptionErrorMessage()
    {
        $errorMessage = static::$option . " is not a number from the options! Try again!";

        if (static::isRunningFromCommandLine()) {
            echo $errorMessage . static::getLineEnding();
            static::run();
        } else {
            echo '<script>alert("' . $errorMessage . '")</script>';
            echo '<script>window.location.replace(window.location.href.split("?")[0])</script>';
        }
    }

    public static function switchTheme()
    {
        if (static::hasSelectedAValidOption()) {
            static::printResultOf(static::downloadTheme());
        } else {
            static::printInvalidOptionErrorMessage();
        }
    }
}

AdminerThemeSwitcher::run();

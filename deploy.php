<?php
/**
 * Simple PHP Git deploy script
 *
 * Automatically deploy the code using PHP and Git.
 *
 * @version 1.3.1
 * @link  https://github.com/476552238li/deploy
 */

// =========================================[ Configuration start ]===

/**
 * It's preferable to configure the script using `deploy-config.php` file.
 *
 * Rename `deploy-config.example.php` to `deploy-config.php` and edit the
 * configuration options there instead of here. That way, you won't have to edit
 * the configuration again if you download the new version of `deploy.php`.
 */
require_once basename(__FILE__, '.php') . '-config.php';

################################test data########################################

$_GET['sat'] = 'bandit';

################################test data########################################
// ===========================================[ Configuration end ]===

// If there's authorization error, set the correct HTTP header.
if (!isset($_GET['sat']) || $_GET['sat'] !== SECRET_ACCESS_TOKEN || SECRET_ACCESS_TOKEN === '6a604a35d5a7c3fd8786f5ee94991a8c') {
    header('HTTP/1.0 403 Forbidden');
}
ob_start();
echo "Simple PHP Git deploy script", PHP_EOL;
echo "Checking the environment ...", PHP_EOL;
$tmp = exec('whoami', $tmp);
echo trim($tmp), PHP_EOL;

// Check if the required programs are available
$requiredBinaries = array('git', 'rsync');
if (defined('BACKUP_DIR') && BACKUP_DIR !== false) {
    $requiredBinaries[] = 'tar';
    if (!is_dir(BACKUP_DIR) || !is_writable(BACKUP_DIR)) {
        die(sprintf('BACKUP_DIR `%s` does not exists or is not writeable.', BACKUP_DIR));
    }
}
if (defined('USE_COMPOSER') && USE_COMPOSER === true) {
    $requiredBinaries[] = 'composer --no-ansi';
}
foreach ($requiredBinaries as $command) {
    $path = trim(exec('which ' . $command));
    if ($path == '') {
        die(sprintf('%s  not available. It needs to be installed on the server for this script to work.', $command));
    } else {
        unset($v);
        exec($command . ' --version', $v);
        $version = explode("\n", end($v));
        echo $path, $version[0], PHP_EOL;
    }
}
echo "Environment OK.", PHP_EOL;
echo "Deploying  ", REMOTE_REPOSITORY, "\t", BRANCH, PHP_EOL;
echo "to ", TARGET_DIR, PHP_EOL;
// The commands
$commands = array();

// ========================================[ Pre-Deployment steps ]===

if (!is_dir(TMP_DIR)) {
    // Clone the repository into the TMP_DIR
    $commands[] = sprintf(
        'git clone --depth=1 --branch %s %s %s'
        , BRANCH
        , REMOTE_REPOSITORY
        , TMP_DIR
    );
} else {
    // TMP_DIR exists and hopefully already contains the correct remote origin
    // so we'll fetch the changes and reset the contents.
    $commands[] = sprintf(
        'git --git-dir="%s.git" --work-tree="%s" fetch origin %s'
        , TMP_DIR
        , TMP_DIR
        , BRANCH
    );
    $commands[] = sprintf(
        'git --git-dir="%s.git" --work-tree="%s" reset --hard FETCH_HEAD'
        , TMP_DIR
        , TMP_DIR
    );
}

// Update the submodules
$commands[] = sprintf(
    'git submodule update --init --recursive'
);

// Describe the deployed version
if (defined('VERSION_FILE') && VERSION_FILE !== '') {
    $commands[] = sprintf(
        'git --git-dir="%s.git" --work-tree="%s" describe --always > %s'
        , TMP_DIR
        , TMP_DIR
        , VERSION_FILE
    );
}

// Backup the TARGET_DIR
// without the BACKUP_DIR for the case when it's inside the TARGET_DIR
if (defined('BACKUP_DIR') && BACKUP_DIR !== false) {
    $commands[] = sprintf(
        "tar --exclude='%s*' -czf %s/%s-%s-%s.tar.gz %s*"
        , BACKUP_DIR
        , BACKUP_DIR
        , basename(TARGET_DIR)
        , md5(TARGET_DIR)
        , date('YmdHis')
        , TARGET_DIR // We're backing up this directory into BACKUP_DIR
    );
}

// Invoke composer
if (defined('USE_COMPOSER') && USE_COMPOSER === true) {
    $commands[] = sprintf(
        'composer --no-ansi --no-interaction --no-progress --working-dir=%s install %s'
        , TMP_DIR
        , (defined('COMPOSER_OPTIONS')) ? COMPOSER_OPTIONS : ''
    );
    if (defined('COMPOSER_HOME') && is_dir(COMPOSER_HOME)) {
        putenv('COMPOSER_HOME=' . COMPOSER_HOME);
    }
}

// ==================================================[ Deployment ]===

// Compile exclude parameters
$exclude = '';
foreach (unserialize(EXCLUDE) as $exc) {
    $exclude .= ' --exclude=' . $exc;
}

// Deployment command
$commands[] = sprintf(
    'rsync -rltgoDzvO %s %s %s %s'
    , TMP_DIR
    , TARGET_DIR
    , (DELETE_FILES) ? '--delete-after' : ''
    , $exclude
);

// =======================================[ Post-Deployment steps ]===

// Remove the TMP_DIR (depends on CLEAN_UP)
if (CLEAN_UP) {
    $commands['cleanup'] = sprintf(
        'rm -rf %s'
        , TMP_DIR
    );
}

// =======================================[ Run the command steps ]===
$output = '';
foreach ($commands as $command) {
    set_time_limit(TIME_LIMIT); // Reset the time limit for each command
    if (file_exists(TMP_DIR) && is_dir(TMP_DIR)) {
        chdir(TMP_DIR); // Ensure that we're in the right directory
    }
    $tmp = array();
    exec($command . ' 2>&1', $tmp, $return_code); // Execute the command
    // Output the result
    printf('
        $ %s
         %s
        '
        , htmlentities(trim($command))
        , htmlentities(trim(implode("\n", $tmp)))
    );
    $output .= ob_get_contents();
    ob_flush(); // Try to output everything as it happens

    // Error handling and cleanup
    if ($return_code !== 0) {
        printf('
            Error encountered!
            Stopping the script to prevent possible data loss.
            CHECK THE DATA IN YOUR TARGET DIR!
          '
        );
        if (CLEAN_UP) {
            exec($commands['cleanup'], $tmp);
            printf('
            Cleaning up temporary files ...

            $ %s
            %s
            '
                , htmlentities(trim($commands['cleanup']))
                , htmlentities(trim($tmp))
            );
        }
        $error = sprintf(
            'Deployment error on %s using '
            , __FILE__
        );
        error_log($error);
        if (EMAIL_ON_ERROR) {
            $output .= ob_get_contents();
            $headers = array();
            $headers[] = sprintf('From: Simple PHP Git deploy script <simple-php-git-deploy@%s>', $_SERVER['HTTP_HOST']);
            $headers[] = sprintf('X-Mailer: PHP/%s', phpversion());
            mail(EMAIL_ON_ERROR, $error, strip_tags(trim($output)), implode("\r\n", $headers));
        }
        break;
    }
}
echo "Done.confilict test", PHP_EOL;
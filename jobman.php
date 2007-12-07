<?php
/*
 * PHPJobMan
 *
 * This is a php job manager requiring the process control functions. You
 * can compile php with the --enable-pcntl flag or just use xampp. The
 * configurable options are:
 *
 * concurrency: # of simultaneous processes
 * sleepinterval: seconds of sleep betwixt process reaping
 * runseconds: seconds before we start shooting
 * shellscript: the script to run using system()
 *
 * It writes to syslog with an ident of "phpjobman". I suggest running
 * from daemontools with multilog.
 *
 * jay@runciblelabs.com
 *
 */

$concurrency = 3;
$sleepinterval = 1;
$runseconds = 2;
$shellscript = "test.sh";

/* end of configurable items, you are now modifying the program. */

declare(ticks = 1);
openlog('phpjobman', LOG_PID, LOG_USER);
$children = array();

while (true) {
    if (count($children) <= $concurrency) {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die("pcntl_fork failed");
        } else if ($pid == 0) {
            /* child */
            system($shellscript, $ret);
            exit($ret);
        } else {
            /* parent */
            $children[$pid] = microtime(true);
        }
    }
    foreach ($children as $child => $start) {
        if (!posix_kill($child, 0)) {
            $elapsed = microtime(true) - $children[$child];
            pcntl_waitpid($child, $status);
            if (pcntl_wifexited($status)) {
                $es = pcntl_wexitstatus($status);
                syslog(LOG_NOTICE, "$shellscript exit($es) seconds($elapsed)");
            } else {
                $msg = "child($child) abnormal exit";
                if (pcntl_wifsignaled($status)) {
                    $msg .= " : exited with signal " . pcntl_wtermsig($status);
                }
                syslog(LOG_WARNING, $msg);
            }
            unset($children[$child]);
        } else if (microtime(true) - $start > $runseconds) {
            $res = posix_kill($child, SIGTERM);
            syslog(LOG_ERR, "$shellscript exceeded $runseconds seconds");
        }
    }
    sleep($sleepinterval);
}
?>

<div class="exception-summary <?php 
echo !$exception_message ? 'exception-without-message' : '';
?>">
    <div class="exception-metadata">
        <div class="container">
            <h2 class="exception-hierarchy">
                <?php 
foreach (array_reverse($exception->get_all_previous(), true) as $index => $previous_exception) {
    ?>
                    <a href="#trace-box-<?php 
    echo $index + 2;
    ?>"><?php 
    echo $this->abbr_class($previous_exception->get_class());
    ?></a>
                    <span class="icon"><?php 
    echo $this->include('assets/images/chevron-right.svg');
    ?></span>
                <?php 
}
?>
                <a href="#trace-box-1"><?php 
echo $this->abbr_class($exception->get_class());
?></a>
            </h2>
            <h2 class="exception-http">
                HTTP <?php 
echo $status_code;
?> <small><?php 
echo $status_text;
?></small>
            </h2>
        </div>
    </div>
    <div class="exception-message-wrapper">
        <div class="container">
            <h1 class="break-long-words exception-message<?php 
echo mb_strlen((string) $exception_message) > 180 ? ' long' : '';
?>"><?php 
echo $this->format_file_from_text(nl2br((string) $exception_message));
?></h1>

            <div class="exception-illustration hidden-xs-down">
                <?php 
echo $this->include('assets/images/symfony-ghost.svg.php');
?>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="sf-tabs">
        <div class="tab">
            <?php 
$exception_as_array = $exception->to_array();
$exception_with_user_code = [];
$exception_as_array_count = count($exception_as_array);
$last = $exception_as_array_count - 1;
foreach ($exception_as_array as $i => $e) {
    foreach ($e['trace'] as $trace) {
        if ($trace['file'] && !str_contains((string) $trace['file'], '/vendor/') && !str_contains((string) $trace['file'], '/var/cache/') && $i < $last) {
            $exception_with_user_code[] = $i;
        }
    }
}
?>
            <h3 class="tab-title">
                <?php 
if ($exception_as_array_count > 1) {
    ?>
                    Exceptions <span class="badge"><?php 
    echo $exception_as_array_count;
    ?></span>
                <?php 
} else {
    ?>
                    Exception
                <?php 
}
?>
            </h3>

            <div class="tab-content">
                <?php 
foreach ($exception_as_array as $i => $e) {
    echo $this->include('views/traces.html.php', ['exception' => $e, 'index' => $i + 1, 'expand' => in_array($i, $exception_with_user_code, true) || [] === $exception_with_user_code && 0 === $i]);
}
?>
            </div>
        </div>

        <?php 
if ($logger) {
    ?>
        <div class="tab <?php 
    echo !$logger->get_logs() ? 'disabled' : '';
    ?>">
            <h3 class="tab-title">
                Logs
                <?php 
    if ($logger->count_errors()) {
        ?><span class="badge status-error"><?php 
        echo $logger->count_errors();
        ?></span><?php 
    }
    ?>
            </h3>

            <div class="tab-content">
                <?php 
    if ($logger->get_logs()) {
        ?>
                    <?php 
        echo $this->include('views/logs.html.php', ['logs' => $logger->get_logs()]);
        ?>
                <?php 
    } else {
        ?>
                    <div class="empty">
                        <p>No log messages</p>
                    </div>
                <?php 
    }
    ?>
            </div>
        </div>
        <?php 
}
?>

        <div class="tab">
            <h3 class="tab-title">
                <?php 
if ($exception_as_array_count > 1) {
    ?>
                    Stack Traces <span class="badge"><?php 
    echo $exception_as_array_count;
    ?></span>
                <?php 
} else {
    ?>
                    Stack Trace
                <?php 
}
?>
            </h3>

            <div class="tab-content">
                <?php 
foreach ($exception_as_array as $i => $e) {
    echo $this->include('views/traces_text.html.php', ['exception' => $e, 'index' => $i + 1, 'numExceptions' => $exception_as_array_count]);
}
?>
            </div>
        </div>

        <?php 
if ($current_content) {
    ?>
        <div class="tab">
            <h3 class="tab-title">Output content</h3>

            <div class="tab-content">
                <?php 
    echo $current_content;
    ?>
            </div>
        </div>
        <?php 
}
?>
    </div>
</div>

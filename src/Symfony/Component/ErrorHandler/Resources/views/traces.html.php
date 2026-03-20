<div class="trace trace-as-html" id="trace-box-<?php 
echo $index;
?>">
    <div class="trace-details">
        <div class="trace-head">
            <div class="sf-toggle" data-toggle-selector="#trace-html-<?php 
echo $index;
?>" data-toggle-initial="<?php 
echo $expand ? 'display' : '';
?>">
                <span class="icon icon-close"><?php 
echo $this->include('assets/images/icon-minus-square-o.svg');
?></span>
                <span class="icon icon-open"><?php 
echo $this->include('assets/images/icon-plus-square-o.svg');
?></span>
                <?php 
$separator = strrpos((string) $exception['class'], '\\');
$separator = false === $separator ? 0 : $separator + 1;
$namespace = substr((string) $exception['class'], 0, $separator);
$class = substr((string) $exception['class'], $separator);
?>
                <?php 
if ('' === $class) {
    ?>
                    <br>
                <?php 
} else {
    ?>
                    <h3 class="trace-class">
                        <?php 
    if ('' !== $namespace) {
        ?>
                            <span class="trace-namespace"><?php 
        echo $namespace;
        ?></span>
                        <?php 
    }
    ?>
                        <?php 
    echo $class;
    ?>
                    </h3>
                <?php 
}
?>
                <?php 
if ($exception['message'] && $index > 1) {
    ?>
                    <p class="break-long-words trace-message"><?php 
    echo $this->escape($exception['message']);
    ?></p>
                <?php 
}
?>
            </div>
            <?php 
if (\count($exception['data'] ?? [])) {
    ?>
                <details class="exception-properties-wrapper">
                    <summary>Show exception properties</summary>
                    <div class="exception-properties">
                        <?php 
    echo $this->dump_value($exception['data']);
    ?>
                    </div>
                </details>
            <?php 
}
?>
        </div>

        <div id="trace-html-<?php 
echo $index;
?>" class="sf-toggle-content">
        <?php 
$is_first_user_code = true;
foreach ($exception['trace'] as $i => $trace) {
    $is_vendor_trace = $trace['file'] && (str_contains((string) $trace['file'], '/vendor/') || str_contains((string) $trace['file'], '/var/cache/'));
    $display_code_snippet = $is_first_user_code && !$is_vendor_trace;
    if ($display_code_snippet) {
        $is_first_user_code = false;
    }
    ?>
            <div class="trace-line <?php 
    echo $is_vendor_trace ? 'trace-from-vendor' : '';
    ?>">
                <?php 
    echo $this->include('views/trace.html.php', ['prefix' => $index, 'i' => $i, 'trace' => $trace, 'style' => $is_vendor_trace ? 'compact' : ($display_code_snippet ? 'expanded' : '')]);
    ?>
            </div>
            <?php 
}
?>
        </div>
    </div>
</div>

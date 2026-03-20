<div class="trace-line-header break-long-words <?php 
echo $trace['file'] ? 'sf-toggle' : '';
?>" data-toggle-selector="#trace-html-<?php 
echo $prefix;
?>-<?php 
echo $i;
?>" data-toggle-initial="<?php 
echo 'expanded' === $style ? 'display' : '';
?>">
    <?php 
if ($trace['file']) {
    ?>
        <span class="icon icon-close"><?php 
    echo $this->include('assets/images/icon-minus-square.svg');
    ?></span>
        <span class="icon icon-open"><?php 
    echo $this->include('assets/images/icon-plus-square.svg');
    ?></span>
    <?php 
}
?>

    <?php 
if ('compact' !== $style && $trace['function']) {
    ?>
        <span class="trace-class"><?php 
    echo $this->abbr_class($trace['class']);
    ?></span><?php 
    if ($trace['type']) {
        ?><span class="trace-type"><?php 
        echo $trace['type'];
        ?></span><?php 
    }
    ?><span class="trace-method"><?php 
    echo $trace['function'];
    ?></span><?php 
    if (isset($trace['args'])) {
        ?><span class="trace-arguments">(<?php 
        echo $this->format_args($trace['args']);
        ?>)</span><?php 
    }
    ?>
    <?php 
}
?>

    <?php 
if ($trace['file']) {
    ?>
        <?php 
    $line_number = $trace['line'] ?: 1;
    $file_link = $this->file_link_format->format($trace['file'], $line_number);
    $file_path = strtr(strip_tags((string) $this->format_file($trace['file'], $line_number)), [' at line ' . $line_number => '']);
    $file_path_parts = explode(\DIRECTORY_SEPARATOR, $file_path);
    ?>
        <span class="block trace-file-path">
            in
            <a href="<?php 
    echo $file_link;
    ?>">
                <?php 
    echo implode(\DIRECTORY_SEPARATOR, array_slice($file_path_parts, 0, -1)) . \DIRECTORY_SEPARATOR;
    ?><strong><?php 
    echo end($file_path_parts);
    ?></strong>
            </a>
            <?php 
    if ('compact' === $style && $trace['function']) {
        ?>
                <span class="trace-type"><?php 
        echo $trace['type'];
        ?></span>
                <span class="trace-method"><?php 
        echo $trace['function'];
        ?></span>
            <?php 
    }
    ?>
            (line <?php 
    echo $line_number;
    ?>)
            <span class="icon icon-copy hidden" data-clipboard-text="<?php 
    echo implode(\DIRECTORY_SEPARATOR, $file_path_parts) . ':' . $line_number;
    ?>">
                <?php 
    echo $this->include('assets/images/icon-copy.svg');
    ?>
            </span>
        </span>
    <?php 
}
?>
</div>
<?php 
if ($trace['file']) {
    ?>
    <div id="trace-html-<?php 
    echo $prefix . '-' . $i;
    ?>" class="trace-code sf-toggle-content">
        <?php 
    echo strtr($this->file_excerpt($trace['file'], $trace['line'], 5), ['#DD0000' => 'var(--highlight-string)', '#007700' => 'var(--highlight-keyword)', '#0000BB' => 'var(--highlight-default)', '#FF8000' => 'var(--highlight-comment)']);
    ?>
    </div>
<?php 
}
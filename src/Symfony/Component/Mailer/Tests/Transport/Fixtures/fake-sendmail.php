#!/usr/bin/env php
<?php

declare(strict_types=1);
$argsPath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sendmail_args';

file_put_contents($argsPath, implode(' ', $argv));

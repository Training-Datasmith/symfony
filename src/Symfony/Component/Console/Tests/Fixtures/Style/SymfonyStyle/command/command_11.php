<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// ensure long words are properly wrapped in blocks
return function (InputInterface $input, OutputInterface $output): int {
    $word = 'Lopadotemachoselachogaleokranioleipsanodrimhypotrimmatosilphioparaomelitokatakechymenokichlepikossyphophattoperisteralektryonoptekephalliokigklopeleiolagoiosiraiobaphetraganopterygon';
    $sfStyle = new SymfonyStyle($input, $output);
    $sfStyle->block($word, 'CUSTOM', 'fg=white;bg=blue', ' § ', false);

    return 0;
};

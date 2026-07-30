<?php
echo "Running migrations diff...\n";
$output1 = shell_exec('php bin/console doctrine:migrations:diff 2>&1');
echo $output1 . "\n";

echo "Running migrations migrate...\n";
$output2 = shell_exec('php bin/console doctrine:migrations:migrate --no-interaction 2>&1');
echo $output2 . "\n";
echo "Done!\n";

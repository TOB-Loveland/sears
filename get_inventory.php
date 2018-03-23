<?php
require '../client.php';

$client = new SearsClient();

print_r($client->getInventory());
<?php

namespace App;

/** Thrown when a product id does not exist. public/index.php turns it into a 404. */
final class NotFoundException extends \RuntimeException
{
}

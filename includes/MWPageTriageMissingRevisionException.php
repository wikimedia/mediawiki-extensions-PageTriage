<?php

namespace MediaWiki\Extension\PageTriage;

use Exception;

/**
 * Custom exception for missing revisions when adding pages
 * to the PageTriage queue.
 */
class MWPageTriageMissingRevisionException extends Exception {
}

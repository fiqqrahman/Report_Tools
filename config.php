<?php
/**
 * Gemini API configuration.
 *
 * Configure GEMINI_API_KEY in the PHP/Apache environment.
 * Do not commit API keys to this repository.
 */
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', trim((string) getenv('GEMINI_API_KEY')));
}

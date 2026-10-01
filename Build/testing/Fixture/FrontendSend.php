<?php

/*
 * Lab-only USER_INT entry point for the test matrix. Never part of the shipped
 * extension. Sends one real message in FRONTEND context, where the transport
 * reads its credentials from TypoScript (here: := getEnv(...) only).
 */

namespace OliverKroener\OkExchange365\TestFixture;

final class FrontendSend
{
    /**
     * Set by TYPO3 (property on 9-11, setter on 12+). Unused.
     *
     * @var mixed
     */
    public $cObj;

    /**
     * @param mixed $cObj
     */
    public function setContentObjectRenderer($cObj): void
    {
        $this->cObj = $cObj;
    }

    /**
     * @param mixed $content
     * @param mixed $conf
     * @param mixed $request
     */
    // TYPO3 14 only calls userFunc targets that carry this attribute. On PHP 7.4
    // (TYPO3 9/10 labs) the line below is a comment.
    #[\TYPO3\CMS\Core\Attribute\AsAllowedCallable]
    public function main($content = '', $conf = [], $request = null): string
    {
        $token = (string)getenv('EX365_MATRIX_TOKEN');
        if ($token === '' || !hash_equals($token, (string)($_GET['ex365token'] ?? ''))) {
            return 'EX365-NOOP';
        }

        $context = ($_GET['ex365mode'] ?? '') === 'missing' ? 'frontend-missing-env' : 'frontend-getenv';

        try {
            return 'EX365-OK ' . htmlspecialchars(MatrixMail::send($context));
        } catch (\Throwable $e) {
            return 'EX365-FAIL ' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage());
        }
    }
}

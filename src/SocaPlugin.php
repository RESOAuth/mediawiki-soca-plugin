<?php
declare(strict_types=1);
namespace MediaWiki\Extension\SOCA;

use MediaWiki\Extension\PluggableAuth\PluggableAuth;
use MediaWiki\User\UserIdentity;
use RESOAuth\Soca\Failure;

final class SocaPlugin extends PluggableAuth {
    public function authenticate(?int &$id, ?string &$username, ?string &$realname, ?string &$email, ?string &$errorMessage): bool {
        try {
            $context = \RequestContext::getMain(); $session = $context->getRequest()->getSession(); $service = new Service();
            $id = $service->takeLogin($session);
            if ($id !== null) {
                // Null profile fields preserve existing local edits in PluggableAuth.
                $username = $realname = $email = null; return true;
            }
            $url = $service->begin($session, false);
            // PluggableAuth's protocol interface is synchronous. Finish this response
            // after the native session has saved, as its existing OIDC providers do.
            $session->save(); $context->getRequest()->response()->header('Location: ' . $url, true, 302);
            exit;
        } catch (Failure $e) { $errorMessage = 'SOCA sign-in could not complete. Reference: ' . $e->reason; return false; }
        catch (\Throwable $e) { $errorMessage = 'SOCA is unavailable. Use local sign-in or contact an administrator.'; return false; }
    }
    public function deauthenticate(UserIdentity &$user): void { /* Native local logout remains available during provider outages. */ }
    public function saveExtraAttributes(int $id): void { /* Accounts and links are committed together before handoff. */ }
}

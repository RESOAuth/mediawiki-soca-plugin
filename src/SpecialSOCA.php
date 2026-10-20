<?php
declare(strict_types=1);
namespace MediaWiki\Extension\SOCA;

use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use RESOAuth\Soca\{Failure, Json, Policy};

/** Public documents/callback plus an administrator-only native settings page. */
final class SpecialSOCA extends SpecialPage {
    public function __construct() { parent::__construct('SOCA'); }
    public function execute($subPage): void {
        $this->setHeaders(); $service = new Service(); $request = $this->getRequest();
        try {
            if (in_array($subPage, ['metadata', 'jwks'], true)) {
                $client = $service->configuration->client();
                $this->getOutput()->disable();
                $response = $request->response();
                $response->header('Content-Type: ' . ($subPage === 'jwks' ? 'application/jwk-set+json' : 'application/json') . '; charset=utf-8');
                $response->header('Cache-Control: public, max-age=60, must-revalidate');
                $response->header('X-Content-Type-Options: nosniff');
                echo Json::canonical($subPage === 'jwks' ? $client->jwks() : $client->metadata()); return;
            }
            if ($subPage === 'callback') {
                $this->getOutput()->setCdnMaxage(0);
                $request->response()->header('Cache-Control: no-store');
                $request->response()->header('Referrer-Policy: no-referrer');
                $query = [];
                foreach (['state', 'code', 'iss', 'error'] as $name) { if ($request->getVal($name) !== null) { $query[$name] = $request->getVal($name); } }
                $test = $service->callback($request->getSession(), $query, $this->getUser()->getId());
                $this->getOutput()->redirect(SpecialPage::getTitleFor($test ? 'SOCA' : 'PluggableAuthLogin')->getFullURL()); return;
            }
            $this->requireLogin();
            $this->getOutput()->setCdnMaxage(0); $request->response()->header('Cache-Control: no-store');
            if (!$this->getUser()->isAllowed('soca-manage')) { throw new Failure('administrator_permission_required'); }
            $saved = $service->store->settings(); $effective = $service->configuration->effective(); $deployment = $service->configuration->deployment();
            if ($request->wasPosted()) {
                Failure::require($this->getUser()->matchEditToken($request->getVal('token', ''), 'soca'), 'csrf');
                $action = $request->getVal('soca_action');
                if ($action === 'save') {
                    $candidate = $saved['value'];
                    foreach (self::fields() as $name => $label) {
                        if (array_key_exists($name, $deployment)) { continue; }
                        if (in_array($name, ['enabled', 'provision'], true)) { $candidate[$name] = $request->getBool($name); }
                        elseif (str_ends_with($name, '_domains') || str_ends_with($name, '_emails')) { $candidate[$name] = array_values(array_filter(array_map('trim', explode(',', $request->getText($name))))); }
                        else { $candidate[$name] = trim($request->getText($name)); }
                    }
                    $service->configuration->save($candidate, $request->getInt('revision'));
                    $this->getOutput()->addHTML(Html::element('p', ['role' => 'status'], 'Settings saved. Changed security settings require fresh checks and a new test login.'));
                } elseif ($action === 'check') {
                    $client = $service->configuration->client(); $client->checkDocuments(); $service->store->evidence('documents', $client->revision());
                    $this->getOutput()->addHTML(Html::element('p', ['role' => 'status'], 'Discovery and public documents passed. A successful candidate test login is also required.'));
                } elseif ($action === 'test') {
                    $url = $service->begin($request->getSession(), true, $this->getUser()->getId());
                    $this->getOutput()->redirect($url); return;
                } elseif ($action === 'reset-proxy') {
                    Failure::require(!array_key_exists('proxy_template', $deployment), 'deployment_setting_locked');
                    $candidate = $saved['value']; $candidate['proxy_template'] = 'https://dynamic-cimd.resoauth.cloud/cimd/%s'; $candidate['enabled'] = false;
                    $service->configuration->save($candidate, $request->getInt('revision'));
                } elseif ($action === 'disable') {
                    Failure::require(!array_key_exists('enabled', $deployment), 'deployment_setting_locked_use_emergency_environment');
                    $candidate = $saved['value']; $candidate['enabled'] = false; $service->configuration->save($candidate, $saved['revision']);
                } else { throw new Failure('unknown_admin_action'); }
                $saved = $service->store->settings(); $effective = $service->configuration->effective();
            }
            $this->getOutput()->addHTML(Html::element('p', [], 'Deployment settings override saved settings and appear read-only. Private keys are supplied by SOCA_PRIVATE_KEY_FILE. Local sign-in remains enabled.'));
            if (Configuration::emergency()) { $this->getOutput()->addHTML(Html::element('p', ['role' => 'alert'], 'Emergency disable is active. Both ordinary and test sign-in are blocked.')); }
            $html = Html::openElement('form', ['method' => 'post', 'action' => $this->getPageTitle()->getLocalURL()]);
            $html .= Html::hidden('token', $this->getUser()->getEditToken('soca')) . Html::hidden('revision', (string)$saved['revision']);
            foreach (self::fields() as $name => $label) {
                $value = $effective[$name] ?? ''; $locked = array_key_exists($name, $deployment);
                $id = 'soca-' . $name; $html .= Html::openElement('div', ['class' => 'mw-htmlform-field-HTMLTextField']);
                $html .= Html::element('label', ['for' => $id], $label . ($locked ? ' (deployment)' : ''));
                if (in_array($name, ['enabled', 'provision'], true)) {
                    $html .= Html::input($name, '1', 'checkbox', ['id' => $id, 'checked' => $value === true, 'disabled' => $locked]);
                } else {
                    $html .= Html::input($name, is_array($value) ? implode(', ', $value) : (string)$value, 'text', ['id' => $id, 'size' => 80, 'readonly' => $locked]);
                }
                $html .= Html::closeElement('div');
            }
            foreach (['save' => 'Save settings', 'check' => 'Check connection and documents', 'test' => 'Test candidate login', 'disable' => 'Disable SSO', 'reset-proxy' => 'Reset proxy default'] as $action => $label) {
                $html .= Html::element('button', ['type' => 'submit', 'name' => 'soca_action', 'value' => $action], $label) . ' ';
            }
            $html .= Html::closeElement('form');
            try {
                $client = $service->configuration->client();
                $html .= Html::element('h2', [], 'Candidate status');
                $html .= Html::element('p', [], 'Revision: ' . $client->revision());
                foreach (['documents', 'login'] as $kind) {
                    $evidence = $service->store->settings('evidence:' . $kind)['value'];
                    $current = ($evidence['revision'] ?? '') === $client->revision() && ($evidence['at'] ?? 0) > 0;
                    $html .= Html::element('p', [], ucfirst($kind) . ': ' . ($current ? 'current' : 'required'));
                }
                $html .= Html::element('p', [], 'Public client ID preview. Proxy URLs contain public metadata and are visible to the proxy:');
                $html .= Html::element('textarea', ['readonly' => true, 'rows' => 4, 'cols' => 100, 'aria-label' => 'Public client ID'], $client->clientId());
                $html .= Html::element('p', [], 'Identity linking, unlinking, policy preview and redacted status are available through maintenance/soca.php. See the operator guide.');
            } catch (Failure $e) { $html .= Html::element('p', ['role' => 'alert'], 'Candidate is incomplete. Reference: ' . $e->reason); }
            $this->getOutput()->addHTML($html);
        } catch (Failure $e) {
            $this->getOutput()->addHTML(Html::element('p', ['role' => 'alert'], 'SOCA could not complete this action. Reference: ' . $e->reason));
        } catch (\Throwable $e) {
            $this->getOutput()->addHTML(Html::element('p', ['role' => 'alert'], 'SOCA is unavailable. Use local sign-in and review the deployment configuration.'));
            \MediaWiki\Logger\LoggerFactory::getInstance('SOCA')->error('host_operation_failed');
        }
    }
    private static function fields(): array {
        return ['client_name' => 'Application name', 'issuer' => 'Trusted issuer', 'metadata_mode' => 'Metadata mode (hosted or proxy)',
            'proxy_template' => 'HTTPS proxy URL template, exactly one %s in path', 'client_uri' => 'Application website',
            'logo_uri' => 'Public logo URL (optional)', 'policy_uri' => 'Privacy policy URL (optional)', 'tos_uri' => 'Terms URL (optional)',
            'allowed_domains' => 'Allowed email domains, comma separated', 'allowed_emails' => 'Allowed email addresses, comma separated',
            'denied_domains' => 'Denied email domains, comma separated', 'denied_emails' => 'Denied email addresses, comma separated',
            'provision' => 'Create new standard wiki accounts after admission', 'enabled' => 'Enable ordinary SSO after successful checks and test login'];
    }
}

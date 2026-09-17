<?php
/**
 * Gabarit d'e-mail d'invitation — français.
 *
 * Variables disponibles :
 * @var array  $guest    ['token','firstname','lastname','email','group','locale']
 * @var string $link     lien personnel complet (?i=…&lang=fr)
 * @var string $site     URL du site
 * @var string $dateLong « samedi 23 octobre 2027 »
 * @var string $deadline « 23 août 2027 »
 * @var string $contact  adresse de contact des mariés
 *
 * Retourne : ['subject' => …, 'html' => …, 'text' => …]
 */

$contactEsc  = htmlspecialchars($contact, ENT_QUOTES, 'UTF-8');
$contactHtml = $contact !== ''
    ? '<br>Une question ? <a href="mailto:' . $contactEsc . '" style="color:#B08D57;">' . $contactEsc . '</a>'
    : '';
$contactText = $contact !== '' ? "\nUne question ? " . $contact : '';
$prenom = htmlspecialchars($guest['firstname'], ENT_QUOTES, 'UTF-8');
$url    = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
$siteUrl = htmlspecialchars($site, ENT_QUOTES, 'UTF-8');

$subject = 'Julia & Jérémy — 23 octobre 2027 : votre invitation';

$html = <<<HTML
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$subject}</title></head>
<body style="margin:0;padding:0;background:#F7F3EC;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">Votre lien personnel pour confirmer votre présence.</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F3EC;padding:32px 12px;">
<tr><td align="center">

  <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#FFFCF6;border:1px solid #EADFCB;">
    <tr><td bgcolor="#C9AE7C" style="height:6px;background:#C9AE7C;font-size:0;line-height:0;">&nbsp;</td></tr>

    <tr><td align="center" style="padding:36px 36px 8px;">
      <table role="presentation" cellpadding="0" cellspacing="0"><tr>
        <td align="center" width="64" height="64" style="width:64px;height:64px;background:#F3EADC;border:1px solid #C7B08A;border-radius:32px;font-family:'Snell Roundhand',Georgia,serif;font-style:italic;font-size:24px;color:#8E7448;">I J</td>
      </tr></table>
    </td></tr>

    <tr><td align="center" style="padding:20px 36px 0;font-family:Georgia,'Times New Roman',serif;">
      <p style="margin:0;font-size:11px;letter-spacing:5px;text-transform:uppercase;color:#B08D57;">Save the date</p>
      <p style="margin:14px 0 6px;font-size:38px;line-height:1.1;font-style:italic;color:#2F2E2B;">Julia &amp; Jérémy</p>
      <p style="margin:0;font-size:12px;letter-spacing:4px;text-transform:uppercase;color:#5F5A51;">se marient</p>
      <p style="margin:18px 0 0;font-size:17px;letter-spacing:2px;text-transform:uppercase;color:#2F2E2B;">Samedi 23 octobre 2027</p>
      <p style="margin:6px 0 0;font-size:15px;font-style:italic;color:#5F5A51;">Château de Pontarmé — Oise</p>
    </td></tr>

    <tr><td align="center" style="padding:24px 36px 0;">
      <table role="presentation" cellpadding="0" cellspacing="0" width="140"><tr>
        <td style="height:1px;background:#E3D4B6;font-size:0;line-height:0;">&nbsp;</td>
      </tr></table>
    </td></tr>

    <tr><td style="padding:24px 36px 0;font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;color:#2F2E2B;">
      <p style="margin:0 0 14px;">Bonjour {$prenom},</p>
      <p style="margin:0 0 14px;">C'est avec une grande joie que nous vous convions à notre mariage, le <strong style="font-weight:normal;color:#B08D57;">samedi 23 octobre 2027</strong>, au château de Pontarmé.</p>
      <p style="margin:0 0 14px;">Vous trouverez sur notre site le déroulement de la journée, les hébergements à proximité et le formulaire pour confirmer votre présence.</p>
    </td></tr>

    <tr><td align="center" style="padding:26px 36px 8px;">
      <table role="presentation" cellpadding="0" cellspacing="0"><tr>
        <td align="center" bgcolor="#2F2E2B" style="border-radius:2px;">
          <a href="{$url}" style="display:inline-block;padding:16px 34px;font-family:Georgia,'Times New Roman',serif;font-size:13px;letter-spacing:3px;text-transform:uppercase;color:#FFFCF6;text-decoration:none;">Confirmer ma présence</a>
        </td>
      </tr></table>
    </td></tr>

    <tr><td align="center" style="padding:6px 36px 0;font-family:Georgia,'Times New Roman',serif;font-size:12px;color:#5F5A51;">
      <p style="margin:0 0 4px;">Si le bouton ne fonctionne pas, copiez ce lien :</p>
      <p style="margin:0;word-break:break-all;"><a href="{$url}" style="color:#B08D57;">{$url}</a></p>
    </td></tr>

    <tr><td style="padding:24px 36px 0;font-family:Georgia,'Times New Roman',serif;font-size:14px;line-height:1.7;color:#5F5A51;">
      <p style="margin:0 0 10px;padding:14px 16px;background:#F7F3EC;border-left:2px solid #C9AE7C;">Ce lien vous est <strong style="font-weight:normal;color:#2F2E2B;">personnel</strong> : il ouvre votre formulaire et vous permet d'ajouter les personnes qui vous accompagnent. Merci de ne pas le transmettre.</p>
      <p style="margin:14px 0 0;">Nous vous remercions de répondre avant le <strong style="font-weight:normal;color:#2F2E2B;">{$deadline}</strong>.</p>
      <p style="margin:14px 0 0;">Au plaisir de vous compter parmi nous,<br>Julia &amp; Jérémy</p>
    </td></tr>

    <tr><td align="center" style="padding:28px 36px 32px;">
      <table role="presentation" cellpadding="0" cellspacing="0" width="100%"><tr>
        <td style="height:1px;background:#EADFCB;font-size:0;line-height:0;">&nbsp;</td>
      </tr></table>
      <p style="margin:16px 0 0;font-family:Georgia,'Times New Roman',serif;font-size:12px;color:#8C857A;">
        <a href="{$siteUrl}" style="color:#B08D57;text-decoration:none;">{$siteUrl}</a>{$contactHtml}
      </p>
    </td></tr>
  </table>

</td></tr></table>
</body></html>
HTML;

$text = <<<TEXT
JULIA & JÉRÉMY — SAVE THE DATE
Samedi 23 octobre 2027 — Château de Pontarmé (Oise)

Bonjour {$guest['firstname']},

C'est avec une grande joie que nous vous convions à notre mariage,
le samedi 23 octobre 2027, au château de Pontarmé.

Vous trouverez sur notre site le déroulement de la journée, les hébergements
à proximité et le formulaire pour confirmer votre présence :

{$link}

Ce lien vous est personnel : il ouvre votre formulaire et vous permet
d'ajouter les personnes qui vous accompagnent. Merci de ne pas le transmettre.

Nous vous remercions de répondre avant le {$deadline}.

Au plaisir de vous compter parmi nous,
Julia & Jérémy

{$site}{$contactText}
TEXT;

return ['subject' => $subject, 'html' => $html, 'text' => $text];

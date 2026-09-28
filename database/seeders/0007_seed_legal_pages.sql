-- =====================================================================
-- Seed: German legal page templates
-- =====================================================================
--
-- Populates the four required legal pages with structural
-- German templates. These are NOT legal advice. They are
-- starting points the merchant reviews with their lawyer and
-- edits before going live. Every page includes a clear
-- placeholder notice at the top.
--
-- Idempotent: INSERT ... ON DUPLICATE KEY UPDATE. Re-running
-- updates title/body but preserves is_published and updated_by.
--
-- Slugs (required by CheckoutService::missingRequiredSlugs()):
--   impressum    - provider identification (DDG § 5)
--   agb          - terms and conditions (BGB § 312i)
--   widerruf     - withdrawal instruction (EGBGB Art. 246a)
--   datenschutz  - privacy policy (DSGVO Art. 13)
--
-- The widerruf template is the statutory Muster-Widerrufs-
-- belehrung from Anlage 1 zu Art. 246a § 1 Abs. 2 EGBGB.
-- The exact wording is legally mandated; changing it may
-- extend the customer's withdrawal period to 12 months.
-- =====================================================================

-- Placeholder notice prepended to non-statutory pages.
SET @notice = '<div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:1rem;margin-bottom:1.5rem;"><strong>Placeholder template.</strong> This is a structural template, not legal advice. Review it with a qualified German lawyer and replace every bracketed field and every section below before going live.</div>';

-- ---------------------------------------------------------------
-- Impressum (DDG § 5)
-- ---------------------------------------------------------------
INSERT INTO `commerce_legal_pages`
    (`store_id`, `slug`, `title`, `body`, `is_published`)
VALUES (
    1,
    'impressum',
    'Impressum',
    CONCAT(
        @notice,
        '<h2>Angaben gem&auml;&szlig; &sect; 5 DDG</h2>',
        '<p>',
        '[Firmenname / Rechtsform]<br>',
        '[Stra&szlig;e und Hausnummer]<br>',
        '[Postleitzahl und Ort]<br>',
        '[Land]',
        '</p>',
        '<h3>Vertreten durch</h3>',
        '<p>[Name des Gesch&auml;ftsf&uuml;hrers / Vorstands]</p>',
        '<h3>Kontakt</h3>',
        '<p>',
        'Telefon: [Telefonnummer]<br>',
        'E-Mail: [E-Mail-Adresse]',
        '</p>',
        '<h3>Registereintrag</h3>',
        '<p>',
        'Eintragung im Handelsregister.<br>',
        'Registergericht: [Amtsgericht]<br>',
        'Registernummer: [HRB-Nummer]',
        '</p>',
        '<h3>Umsatzsteuer-ID</h3>',
        '<p>Umsatzsteuer-Identifikationsnummer gem&auml;&szlig; &sect; 27a Umsatzsteuergesetz:<br>[DE-Nummer]</p>',
        '<h3>Verantwortlich f&uuml;r den Inhalt nach &sect; 18 Abs. 2 MStV</h3>',
        '<p>[Name und Anschrift]</p>',
        '<h3>EU-Streitschlichtung</h3>',
        '<p>Die Europ&auml;ische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit: https://ec.europa.eu/consumers/odr/. Unsere E-Mail-Adresse finden Sie oben im Impressum.</p>',
        '<p>Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>'
    ),
    1
)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `body`  = VALUES(`body`);

-- ---------------------------------------------------------------
-- AGB (Allgemeine Geschaeftsbedingungen)
-- ---------------------------------------------------------------
INSERT INTO `commerce_legal_pages`
    (`store_id`, `slug`, `title`, `body`, `is_published`)
VALUES (
    1,
    'agb',
    'Allgemeine Gesch&auml;ftsbedingungen',
    CONCAT(
        @notice,
        '<h2>1. Geltungsbereich</h2>',
        '<p>F&uuml;r alle Bestellungen &uuml;ber unseren Online-Shop gelten die nachfolgenden Allgemeinen Gesch&auml;ftsbedingungen (AGB).</p>',
        '<h2>2. Vertragspartner</h2>',
        '<p>Der Kaufvertrag kommt zustande mit [Firmenname], [Anschrift].</p>',
        '<h2>3. Vertragsschluss</h2>',
        '<p>Die Darstellung der Produkte im Online-Shop stellt kein rechtlich bindendes Angebot dar, sondern eine Aufforderung zur Bestellung. Mit dem Absenden der Bestellung geben Sie ein verbindliches Angebot ab. Wir best&auml;tigen den Eingang Ihrer Bestellung unverz&uuml;glich per E-Mail. Diese Best&auml;tigung stellt noch keine Annahme des Angebots dar. Die Annahme erfolgt durch Versand der Ware oder eine ausdr&uuml;ckliche Annahmeerkl&auml;rung.</p>',
        '<h2>4. Preise und Versandkosten</h2>',
        '<p>Alle Preise verstehen sich inklusive der gesetzlichen Mehrwertsteuer. Zus&auml;tzlich anfallende Versandkosten werden im Bestellprozess deutlich mitgeteilt.</p>',
        '<h2>5. Lieferung</h2>',
        '<p>Die Lieferung erfolgt an die vom Kunden angegebene Lieferadresse. Lieferzeiten werden im Bestellprozess angegeben.</p>',
        '<h2>6. Zahlung</h2>',
        '<p>Es stehen die im Bestellprozess angegebenen Zahlungsarten zur Verf&uuml;gung.</p>',
        '<h2>7. Eigentumsvorbehalt</h2>',
        '<p>Die Ware bleibt bis zur vollst&auml;ndigen Bezahlung unser Eigentum.</p>',
        '<h2>8. Widerrufsrecht</h2>',
        '<p>Verbraucher haben ein gesetzliches Widerrufsrecht. N&auml;here Informationen finden Sie in unserer <a href="/legal/widerruf">Widerrufsbelehrung</a>.</p>',
        '<h2>9. Gew&auml;hrleistung</h2>',
        '<p>Es gelten die gesetzlichen Gew&auml;hrleistungsregelungen.</p>',
        '<h2>10. Streitbeilegung</h2>',
        '<p>Die Europ&auml;ische Kommission stellt eine Plattform zur Online-Streitbeilegung bereit. Wir sind nicht verpflichtet, an einem Streitbeilegungsverfahren teilzunehmen.</p>',
        '<h2>11. Schlussbestimmungen</h2>',
        '<p>Es gilt das Recht der Bundesrepublik Deutschland. Gerichtsstand ist [Ort].</p>'
    ),
    1
)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `body`  = VALUES(`body`);

-- ---------------------------------------------------------------
-- Widerrufsbelehrung (statutory template - do not modify)
-- ---------------------------------------------------------------
INSERT INTO `commerce_legal_pages`
    (`store_id`, `slug`, `title`, `body`, `is_published`)
VALUES (
    1,
    'widerruf',
    'Widerrufsbelehrung',
    CONCAT(
        '<h2>Widerrufsrecht</h2>',
        '<p>Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gr&uuml;nden diesen Vertrag zu widerrufen.</p>',
        '<p>Die Widerrufsfrist betr&auml;gt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Bef&ouml;rderer ist, die Waren in Besitz genommen haben bzw. hat.</p>',
        '<p>Um Ihr Widerrufsrecht auszu&uuml;ben, m&uuml;ssen Sie uns</p>',
        '<p>[Firmenname]<br>[Anschrift]<br>Telefon: [Telefonnummer]<br>E-Mail: [E-Mail-Adresse]</p>',
        '<p>mittels einer eindeutigen Erkl&auml;rung (z. B. ein mit der Post versandter Brief oder eine E-Mail) &uuml;ber Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie k&ouml;nnen daf&uuml;r das beigef&uuml;gte Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist.</p>',
        '<p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung &uuml;ber die Aus&uuml;bung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p>',
        '<h3>Folgen des Widerrufs</h3>',
        '<p>Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschlie&szlig;lich der Lieferkosten (mit Ausnahme der zus&auml;tzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, g&uuml;nstigste Standardlieferung gew&auml;hlt haben), unverz&uuml;glich und sp&auml;testens binnen vierzehn Tagen ab dem Tag zur&uuml;ckzuzahlen, an dem die Mitteilung &uuml;ber Ihren Widerruf dieses Vertrags bei uns eingegangen ist.</p>',
        '<p>F&uuml;r diese R&uuml;ckzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der urspr&uuml;nglichen Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdr&uuml;cklich etwas anderes vereinbart; in keinem Fall werden Ihnen wegen dieser R&uuml;ckzahlung Entgelte berechnet.</p>',
        '<p>Wir k&ouml;nnen die R&uuml;ckzahlung verweigern, bis wir die Waren wieder zur&uuml;ckerhalten haben oder bis Sie den Nachweis erbracht haben, dass Sie die Waren zur&uuml;ckgesandt haben, je nachdem, welches der fr&uuml;here Zeitpunkt ist.</p>',
        '<p>Sie haben die Waren unverz&uuml;glich und in jedem Fall sp&auml;testens binnen vierzehn Tagen ab dem Tag, an dem Sie uns &uuml;ber den Widerruf dieses Vertrags unterrichten, an uns zur&uuml;ckzusenden oder zu &uuml;bergeben. Die Frist ist gewahrt, wenn Sie die Waren vor Ablauf der Frist von vierzehn Tagen absenden.</p>',
        '<p>Sie tragen die unmittelbaren Kosten der R&uuml;cksendung der Waren.</p>',
        '<p>Sie m&uuml;ssen f&uuml;r einen etwaigen Wertverlust der Waren nur aufkommen, wenn dieser Wertverlust auf einen zur Pr&uuml;fung der Beschaffenheit, Eigenschaften und Funktionsweise der Waren nicht notwendigen Umgang mit ihnen zur&uuml;ckzuf&uuml;hren ist.</p>',
        '<h3>Muster-Widerrufsformular</h3>',
        '<p>(Wenn Sie den Vertrag widerrufen wollen, dann f&uuml;llen Sie bitte dieses Formular aus und senden Sie es zur&uuml;ck.)</p>',
        '<p>An [Firmenname], [Anschrift], E-Mail: [E-Mail-Adresse]:</p>',
        '<p>Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag &uuml;ber den Kauf der folgenden Waren (*)/die Erbringung der folgenden Dienstleistung (*):</p>',
        '<p>_______________________________________</p>',
        '<p>Bestellt am (*)/erhalten am (*): ____________________</p>',
        '<p>Name des/der Verbraucher(s): ____________________</p>',
        '<p>Anschrift des/der Verbraucher(s): ____________________</p>',
        '<p>Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier): ____________________</p>',
        '<p>Datum: ____________________</p>',
        '<p>(*) Unzutreffendes streichen.</p>'
    ),
    1
)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `body`  = VALUES(`body`);

-- ---------------------------------------------------------------
-- Datenschutzerklaerung (DSGVO Art. 13)
-- ---------------------------------------------------------------
INSERT INTO `commerce_legal_pages`
    (`store_id`, `slug`, `title`, `body`, `is_published`)
VALUES (
    1,
    'datenschutz',
    'Datenschutzerkl&auml;rung',
    CONCAT(
        @notice,
        '<h2>1. Datenschutz auf einen Blick</h2>',
        '<h3>Allgemeine Hinweise</h3>',
        '<p>Die folgenden Hinweise geben einen einfachen &Uuml;berblick dar&uuml;ber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen.</p>',
        '<h3>Datenerfassung auf dieser Website</h3>',
        '<p><strong>Wer ist verantwortlich f&uuml;r die Datenerfassung auf dieser Website?</strong></p>',
        '<p>Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber. Dessen Kontaktdaten k&ouml;nnen Sie dem Abschnitt &bdquo;Hinweis zur Verantwortlichen Stelle&ldquo; in dieser Datenschutzerkl&auml;rung entnehmen.</p>',
        '<p><strong>Wie erfassen wir Ihre Daten?</strong></p>',
        '<p>Ihre Daten werden zum einen dadurch erhoben, dass Sie uns diese mitteilen, z. B. durch Eingabe in ein Bestellformular. Andere Daten werden automatisch oder nach Ihrer Einwilligung beim Besuch der Website durch unsere IT-Systeme erfasst.</p>',
        '<p><strong>Wof&uuml;r nutzen wir Ihre Daten?</strong></p>',
        '<p>Ein Teil der Daten wird erhoben, um eine fehlerfreie Bereitstellung der Website zu gew&auml;hrleisten. Andere Daten k&ouml;nnen zur Analyse Ihres Nutzerverhaltens verwendet werden.</p>',
        '<p><strong>Welche Rechte haben Sie bez&uuml;glich Ihrer Daten?</strong></p>',
        '<p>Sie haben jederzeit das Recht, unentgeltlich Auskunft &uuml;ber Herkunft, Empf&auml;nger und Zweck Ihrer gespeicherten personenbezogenen Daten zu erhalten. Sie haben au&szlig;erdem ein Recht, die Berichtigung oder L&ouml;schung dieser Daten zu verlangen.</p>',
        '<h2>2. Hosting</h2>',
        '<p>Wir hosten die Inhalte unserer Website bei folgendem Anbieter:</p>',
        '<p>[Hosting-Anbieter, Anschrift]</p>',
        '<h2>3. Allgemeine Hinweise und Pflichtinformationen</h2>',
        '<h3>Datenschutz</h3>',
        '<p>Die Betreiber dieser Seiten nehmen den Schutz Ihrer pers&ouml;nlichen Daten sehr ernst. Wir behandeln Ihre personenbezogenen Daten vertraulich und entsprechend der gesetzlichen Datenschutzvorschriften sowie dieser Datenschutzerkl&auml;rung.</p>',
        '<h3>Hinweis zur verantwortlichen Stelle</h3>',
        '<p>Die verantwortliche Stelle f&uuml;r die Datenverarbeitung auf dieser Website ist:</p>',
        '<p>[Firmenname]<br>[Anschrift]<br>Telefon: [Telefonnummer]<br>E-Mail: [E-Mail-Adresse]</p>',
        '<h3>Speicherdauer</h3>',
        '<p>Soweit innerhalb dieser Datenschutzerkl&auml;rung keine speziellere Speicherdauer genannt wurde, verbleiben Ihre personenbezogenen Daten bei uns, bis der Zweck f&uuml;r die Datenverarbeitung entf&auml;llt.</p>',
        '<h2>4. Datenerfassung auf dieser Website</h2>',
        '<h3>Cookies</h3>',
        '<p>Unsere Internetseiten verwenden so genannte &bdquo;Cookies&ldquo;. Cookies sind kleine Datenpakete und richten auf Ihrem Endger&auml;t keinen Schaden an. Sie werden entweder vor&uuml;bergehend f&uuml;r die Dauer einer Sitzung (Session-Cookies) oder dauerhaft (permanente Cookies) auf Ihrem Endger&auml;t gespeichert.</p>',
        '<h3>Bestellungen im Online-Shop</h3>',
        '<p>Wenn Sie bei uns bestellen, erheben wir folgende Daten: Name, Anschrift, E-Mail-Adresse, Telefonnummer, Zahlungsinformationen und Bestelldaten. Diese Daten werden zur Vertragserf&uuml;llung verarbeitet (Art. 6 Abs. 1 lit. b DSGVO).</p>',
        '<h2>5. Ihre Rechte</h2>',
        '<p>Sie haben im Rahmen der geltenden gesetzlichen Bestimmungen jederzeit das Recht auf unentgeltliche Auskunft &uuml;ber Ihre gespeicherten personenbezogenen Daten, deren Herkunft und Empf&auml;nger und den Zweck der Datenverarbeitung und ggf. ein Recht auf Berichtigung oder L&ouml;schung dieser Daten.</p>',
        '<p>Hierzu sowie zu weiteren Fragen zum Thema Datenschutz k&ouml;nnen Sie sich jederzeit an uns wenden.</p>'
    ),
    1
)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `body`  = VALUES(`body`);

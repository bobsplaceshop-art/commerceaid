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
-- updates title/body but preserves is_published.
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
--
-- Content uses native UTF-8 (ä, ö, ü, ß, §, „ "). MySQL 8 with
-- utf8mb4_unicode_ci stores these correctly. No HTML entities.
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
        '<h2>Angaben gemäß § 5 DDG</h2>',
        '<p>',
        '[Firmenname / Rechtsform]<br>',
        '[Straße und Hausnummer]<br>',
        '[Postleitzahl und Ort]<br>',
        '[Land]',
        '</p>',
        '<h3>Vertreten durch</h3>',
        '<p>[Name des Geschäftsführers / Vorstands]</p>',
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
        '<p>Umsatzsteuer-Identifikationsnummer gemäß § 27a Umsatzsteuergesetz:<br>[DE-Nummer]</p>',
        '<h3>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h3>',
        '<p>[Name und Anschrift]</p>',
        '<h3>EU-Streitschlichtung</h3>',
        '<p>Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit: https://ec.europa.eu/consumers/odr/. Unsere E-Mail-Adresse finden Sie oben im Impressum.</p>',
        '<p>Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>'
    ),
    1
)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `body`  = VALUES(`body`);

-- ---------------------------------------------------------------
-- AGB (Allgemeine Geschäftsbedingungen)
-- ---------------------------------------------------------------
INSERT INTO `commerce_legal_pages`
    (`store_id`, `slug`, `title`, `body`, `is_published`)
VALUES (
    1,
    'agb',
    'Allgemeine Geschäftsbedingungen',
    CONCAT(
        @notice,
        '<h2>1. Geltungsbereich</h2>',
        '<p>Für alle Bestellungen über unseren Online-Shop gelten die nachfolgenden Allgemeinen Geschäftsbedingungen (AGB).</p>',
        '<h2>2. Vertragspartner</h2>',
        '<p>Der Kaufvertrag kommt zustande mit [Firmenname], [Anschrift].</p>',
        '<h2>3. Vertragsschluss</h2>',
        '<p>Die Darstellung der Produkte im Online-Shop stellt kein rechtlich bindendes Angebot dar, sondern eine Aufforderung zur Bestellung. Mit dem Absenden der Bestellung geben Sie ein verbindliches Angebot ab. Wir bestätigen den Eingang Ihrer Bestellung unverzüglich per E-Mail. Diese Bestätigung stellt noch keine Annahme des Angebots dar. Die Annahme erfolgt durch Versand der Ware oder eine ausdrückliche Annahmeerklärung.</p>',
        '<h2>4. Preise und Versandkosten</h2>',
        '<p>Alle Preise verstehen sich inklusive der gesetzlichen Mehrwertsteuer. Zusätzlich anfallende Versandkosten werden im Bestellprozess deutlich mitgeteilt.</p>',
        '<h2>5. Lieferung</h2>',
        '<p>Die Lieferung erfolgt an die vom Kunden angegebene Lieferadresse. Lieferzeiten werden im Bestellprozess angegeben.</p>',
        '<h2>6. Zahlung</h2>',
        '<p>Es stehen die im Bestellprozess angegebenen Zahlungsarten zur Verfügung.</p>',
        '<h2>7. Eigentumsvorbehalt</h2>',
        '<p>Die Ware bleibt bis zur vollständigen Bezahlung unser Eigentum.</p>',
        '<h2>8. Widerrufsrecht</h2>',
        '<p>Verbraucher haben ein gesetzliches Widerrufsrecht. Nähere Informationen finden Sie in unserer <a href="/legal/widerruf">Widerrufsbelehrung</a>.</p>',
        '<h2>9. Gewährleistung</h2>',
        '<p>Es gelten die gesetzlichen Gewährleistungsregelungen.</p>',
        '<h2>10. Streitbeilegung</h2>',
        '<p>Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung bereit. Wir sind nicht verpflichtet, an einem Streitbeilegungsverfahren teilzunehmen.</p>',
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
        '<p>Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.</p>',
        '<p>Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat.</p>',
        '<p>Um Ihr Widerrufsrecht auszuüben, müssen Sie uns</p>',
        '<p>[Firmenname]<br>[Anschrift]<br>Telefon: [Telefonnummer]<br>E-Mail: [E-Mail-Adresse]</p>',
        '<p>mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder eine E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie können dafür das beigefügte Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist.</p>',
        '<p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p>',
        '<h3>Folgen des Widerrufs</h3>',
        '<p>Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist.</p>',
        '<p>Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas anderes vereinbart; in keinem Fall werden Ihnen wegen dieser Rückzahlung Entgelte berechnet.</p>',
        '<p>Wir können die Rückzahlung verweigern, bis wir die Waren wieder zurückerhalten haben oder bis Sie den Nachweis erbracht haben, dass Sie die Waren zurückgesandt haben, je nachdem, welches der frühere Zeitpunkt ist.</p>',
        '<p>Sie haben die Waren unverzüglich und in jedem Fall spätestens binnen vierzehn Tagen ab dem Tag, an dem Sie uns über den Widerruf dieses Vertrags unterrichten, an uns zurückzusenden oder zu übergeben. Die Frist ist gewahrt, wenn Sie die Waren vor Ablauf der Frist von vierzehn Tagen absenden.</p>',
        '<p>Sie tragen die unmittelbaren Kosten der Rücksendung der Waren.</p>',
        '<p>Sie müssen für einen etwaigen Wertverlust der Waren nur aufkommen, wenn dieser Wertverlust auf einen zur Prüfung der Beschaffenheit, Eigenschaften und Funktionsweise der Waren nicht notwendigen Umgang mit ihnen zurückzuführen ist.</p>',
        '<h3>Muster-Widerrufsformular</h3>',
        '<p>(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</p>',
        '<p>An [Firmenname], [Anschrift], E-Mail: [E-Mail-Adresse]:</p>',
        '<p>Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*)/die Erbringung der folgenden Dienstleistung (*):</p>',
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
-- Datenschutzerklärung (DSGVO Art. 13)
-- ---------------------------------------------------------------
INSERT INTO `commerce_legal_pages`
    (`store_id`, `slug`, `title`, `body`, `is_published`)
VALUES (
    1,
    'datenschutz',
    'Datenschutzerklärung',
    CONCAT(
        @notice,
        '<h2>1. Datenschutz auf einen Blick</h2>',
        '<h3>Allgemeine Hinweise</h3>',
        '<p>Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen.</p>',
        '<h3>Datenerfassung auf dieser Website</h3>',
        '<p><strong>Wer ist verantwortlich für die Datenerfassung auf dieser Website?</strong></p>',
        '<p>Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber. Dessen Kontaktdaten können Sie dem Abschnitt „Hinweis zur Verantwortlichen Stelle" in dieser Datenschutzerklärung entnehmen.</p>',
        '<p><strong>Wie erfassen wir Ihre Daten?</strong></p>',
        '<p>Ihre Daten werden zum einen dadurch erhoben, dass Sie uns diese mitteilen, z. B. durch Eingabe in ein Bestellformular. Andere Daten werden automatisch oder nach Ihrer Einwilligung beim Besuch der Website durch unsere IT-Systeme erfasst.</p>',
        '<p><strong>Wofür nutzen wir Ihre Daten?</strong></p>',
        '<p>Ein Teil der Daten wird erhoben, um eine fehlerfreie Bereitstellung der Website zu gewährleisten. Andere Daten können zur Analyse Ihres Nutzerverhaltens verwendet werden.</p>',
        '<p><strong>Welche Rechte haben Sie bezüglich Ihrer Daten?</strong></p>',
        '<p>Sie haben jederzeit das Recht, unentgeltlich Auskunft über Herkunft, Empfänger und Zweck Ihrer gespeicherten personenbezogenen Daten zu erhalten. Sie haben außerdem ein Recht, die Berichtigung oder Löschung dieser Daten zu verlangen.</p>',
        '<h2>2. Hosting</h2>',
        '<p>Wir hosten die Inhalte unserer Website bei folgendem Anbieter:</p>',
        '<p>[Hosting-Anbieter, Anschrift]</p>',
        '<h2>3. Allgemeine Hinweise und Pflichtinformationen</h2>',
        '<h3>Datenschutz</h3>',
        '<p>Die Betreiber dieser Seiten nehmen den Schutz Ihrer persönlichen Daten sehr ernst. Wir behandeln Ihre personenbezogenen Daten vertraulich und entsprechend der gesetzlichen Datenschutzvorschriften sowie dieser Datenschutzerklärung.</p>',
        '<h3>Hinweis zur verantwortlichen Stelle</h3>',
        '<p>Die verantwortliche Stelle für die Datenverarbeitung auf dieser Website ist:</p>',
        '<p>[Firmenname]<br>[Anschrift]<br>Telefon: [Telefonnummer]<br>E-Mail: [E-Mail-Adresse]</p>',
        '<h3>Speicherdauer</h3>',
        '<p>Soweit innerhalb dieser Datenschutzerklärung keine speziellere Speicherdauer genannt wurde, verbleiben Ihre personenbezogenen Daten bei uns, bis der Zweck für die Datenverarbeitung entfällt.</p>',
        '<h2>4. Datenerfassung auf dieser Website</h2>',
        '<h3>Cookies</h3>',
        '<p>Unsere Internetseiten verwenden so genannte „Cookies". Cookies sind kleine Datenpakete und richten auf Ihrem Endgerät keinen Schaden an. Sie werden entweder vorübergehend für die Dauer einer Sitzung (Session-Cookies) oder dauerhaft (permanente Cookies) auf Ihrem Endgerät gespeichert.</p>',
        '<h3>Bestellungen im Online-Shop</h3>',
        '<p>Wenn Sie bei uns bestellen, erheben wir folgende Daten: Name, Anschrift, E-Mail-Adresse, Telefonnummer, Zahlungsinformationen und Bestelldaten. Diese Daten werden zur Vertragserfüllung verarbeitet (Art. 6 Abs. 1 lit. b DSGVO).</p>',
        '<h2>5. Ihre Rechte</h2>',
        '<p>Sie haben im Rahmen der geltenden gesetzlichen Bestimmungen jederzeit das Recht auf unentgeltliche Auskunft über Ihre gespeicherten personenbezogenen Daten, deren Herkunft und Empfänger und den Zweck der Datenverarbeitung und ggf. ein Recht auf Berichtigung oder Löschung dieser Daten.</p>',
        '<p>Hierzu sowie zu weiteren Fragen zum Thema Datenschutz können Sie sich jederzeit an uns wenden.</p>'
    ),
    1
)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `body`  = VALUES(`body`);

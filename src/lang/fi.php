<?php

return [
	// Navigation
	'nav.home'                  => 'Etusivu',
	'nav.return'                => 'Takaisin',
	'nav.catalog'               => 'Katalogi',
	'nav.refresh'               => 'Päivitä',
	'nav.bottom'                => 'Alas',
	'nav.top'                   => 'Ylös',
	'nav.hidden'                => 'Piilotetut',
	'nav.gallery'               => 'Galleria',
	'nav.settings'              => 'Asetukset',
	'nav.manage'                => 'Hallinta',
	'nav.previous'              => 'Edellinen',
	'nav.next'                  => 'Seuraava',

	// Post form
	'form.name'                 => 'Nimi',
	'form.email'                => 'Sähköposti',
	'form.mod_options'          => 'Mod-asetukset',
	'form.capcode'              => 'Capcode',
	'form.subject'              => 'Aihe',
	'form.submit'               => 'Lähetä',
	'form.message'              => 'Viesti',
	'form.captcha'              => 'CAPTCHA',
	'form.board'                => 'Lauta',
	'form.file'                 => 'Tiedosto',
	'form.draw'                 => 'Piirrä',
	'form.file_options'         => 'Tiedostoasetukset',
	'form.spoiler'              => 'Spoileri',
	'form.no_filename'          => 'Ei tiedostonimeä',
	'form.embed'                => 'Upota',
	'form.password'             => 'Salasana',
	'form.supported_files'      => 'Tuetut tiedostotyypit ovat :types.',
	'form.supported_embeds'     => 'Tuetut upotetyypit ovat :types.',
	'form.max_filesize'         => 'Tiedoston enimmäiskoko on :size.',
	'form.thumb_info'           => 'Kuvat, jotka ovat suurempia kuin :size, pienennetään.',

	// Thread / Posts
	'post.posting_mode'         => 'Viestitystila:',
	'post.reply'                => 'Vastaa',
	'post.truncated'            => 'Viesti lyhennetty. Klikkaa vastaa nähdäksesi.',
	'post.replies_omitted'      => ':count vastausta piilotettu. Klikkaa vastaa nähdäksesi.',
	'post.hidden'               => 'Viesti piilotettu',
	'post.stickied'             => 'Kiinnitetty',
	'post.locked'               => 'Lukittu',

	// Delete form
	'delete.title'              => 'Poista viesti',
	'delete.password'           => 'Salasana',
	'delete.submit'             => 'Poista',

	// Report form
	'report.notice'             => 'HUOMAUTUS',
	'report.info'               => 'Ilmiannetaan viesti :post_id laudalla /:board_id/.',
	'report.warning'            => 'Väärien tai väärin luokiteltujen ilmiantojen lähettäminen johtaa porttikieltoon.',
	'report.type'               => 'Tyyppi',

	// Board filter
	'filter.toggle'             => 'Suodata lautoja',
	'filter.apply'              => 'Käytä',
	'filter.reset'              => 'Nollaa',

	// Home page
	'home.boards'               => 'Laudat',
	'home.stats'                => 'Tilastot',
	'home.rules'                => 'Säännöt',
	'home.total_posts'          => 'Viestejä yhteensä:',
	'home.current_posts'        => 'Nykyiset viestit:',
	'home.unique_posters'       => 'Yksilöllisiä lähettäjiä:',
	'home.imported_posts'       => 'Tuodut viestit:',
	'home.current_files'        => 'Nykyiset tiedostot:',
	'home.active_content'       => 'Aktiivinen sisältö:',

	// Manage
	'manage.title'              => 'Hallinta',
	'manage.login'              => 'Kirjautuminen',
	'manage.log_in'             => 'Kirjaudu',
	'manage.username'           => 'Käyttäjänimi',
	'manage.password'           => 'Salasana',
	'manage.rebuild'            => 'Uudelleenrakenna',
	'manage.refresh'            => 'Päivitä',
	'manage.import'             => 'Tuo',
	'manage.accounts'           => 'Tilit',
	'manage.logs'               => 'Lokit',
	'manage.csam_hashes'        => 'CSAM-tiivisteet',
	'manage.bans'               => 'Porttikiellot',
	'manage.posts'              => 'Viestit',
	'manage.threads'            => 'Langat',
	'manage.reports'            => 'Ilmiannot',
	'manage.logout'             => 'Kirjaudu ulos',
	'manage.return'             => 'Takaisin',
	'manage.status'             => 'TILA',
	'manage.selected'           => 'Valittu',
	'manage.delete_post'        => 'Poista (viesti)',
	'manage.ban_ip'             => 'Porttikielto (ip)',
	'manage.ban_minutes'        => 'Porttikiellon minuutit',
	'manage.ban_reason'         => 'Porttikiellon syy',
	'manage.mark_csam'          => 'Merkitse CSAM:ksi',
	'manage.toggle_lock'        => 'Lukitse/avaa (lanka)',
	'manage.toggle_sticky'      => 'Kiinnitä/irrota (lanka)',
	'manage.management_iface'   => 'Hallintapaneeli',
	'manage.approve_report'     => 'Hyväksy (ilmianto)',
	'manage.source_db'          => 'Lähdetietokanta',
	'manage.source_table'       => 'Lähdetaulu',
	'manage.target_board'       => 'Kohdelauta',
	'manage.db_name'            => 'DB-nimi',
	'manage.db_user'            => 'DB-käyttäjä',
	'manage.db_pass'            => 'DB-salasana',
	'manage.table_name'         => 'TAULU-nimi',
	'manage.table_type'         => 'TAULU-tyyppi',
	'manage.board_id'           => 'LAUTA-id',

	// Manage table headers
	'manage.th.id'              => 'id',
	'manage.th.post_id'         => 'viesti_id',
	'manage.th.parent_id'       => 'ylä_id',
	'manage.th.board_id'        => 'lauta_id',
	'manage.th.ip'              => 'ip',
	'manage.th.deleted'         => 'poistettu',
	'manage.th.imported'        => 'tuotu',
	'manage.th.preview'         => 'esikatselu',
	'manage.th.timestamp'       => 'aikaleima',
	'manage.th.expire'          => 'vanhenee',
	'manage.th.reason'          => 'syy',
	'manage.th.message'         => 'viesti',
	'manage.th.username'        => 'käyttäjänimi',
	'manage.th.role'            => 'rooli',
	'manage.th.lastactive'      => 'viimeksiaktiivinen',
	'manage.th.type'            => 'tyyppi',
	'manage.th.post_ip'         => 'viesti_ip',
	'manage.th.post_deleted'    => 'viesti_poistettu',
	'manage.th.post_imported'   => 'viesti_tuotu',

	// Bans page
	'bans.title'                => 'Porttikiellot',
	'bans.subtitle'             => 'Julkinen lista viimeisimmistä porttikielloista',
	'bans.heading'              => 'Porttikiellot (viimeiset 2 viikkoa)',

	// Logs page
	'logs.title'                => 'Lokit',
	'logs.subtitle'             => 'Julkinen lista viimeisimmistä lokeista',
	'logs.heading'              => 'Lokit (viimeiset 2 viikkoa)',

	// Error page
	'error.title'               => 'Virhe',
	'error.fallback'            => 'jokin meni pieleen...',

	// File info
	'file.file'                 => 'Tiedosto:',
	'file.embed'                => 'Upote:',
	'file.embedded_url'         => 'Upotettu URL',

	// Style picker
	'style.label'               => 'Tyyli',
];

<?php
/**
 * Magento → WooCommerce: Backfill billing_phone
 *
 * Self-contained — no SQL file needed. All Magento customer and phone
 * data is embedded directly in this script, parsed from 127_0_0_1.sql
 * (152 customers, 121 with telephone, 301 address records).
 *
 * Usage:
 *   wp eval-file backfill-billing-phone.php
 *   wp eval-file backfill-billing-phone.php -- --dry-run
 *
 * What it does:
 *   - Matches WP users to Magento customers by email (case-insensitive)
 *   - Looks up their phone from the Magento address data
 *   - Writes to billing_phone user meta — OVERWRITES any existing value
 *   - Prints a full log and summary
 *
 * To undo: update_user_meta writes are the only change. No tables created.
 *
 * Author: The Hive Craft / Ashok Shrestha
 * Date:   2026-06-24
 */

// ─────────────────────────────────────────────────────────────
// ARGS
// ─────────────────────────────────────────────────────────────

$dry_run = in_array( '--dry-run', $args, true );

if ( $dry_run ) {
    WP_CLI::log( '---- DRY RUN — no data will be written ----' );
}

// ─────────────────────────────────────────────────────────────
// DATA: email (lowercase) → Magento customer ID
// Source: $MAGENTO_CUSTOMERS from import-magento-customers.php
// ─────────────────────────────────────────────────────────────

$magento_email_to_id = [
    'mwiueqmvqiu@yahoo.com'           => 1,
    'dtvgggnohfchgcaqj@yahoo.com'     => 2,
    'eazenithaiglyph61@gmail.com'     => 3,
    'pepisa4752@pixdd.com'            => 5,
    'babypasa-test-001@yopmail.com'   => 6,
    'infinityworld0333@gmail.com'     => 7,
    'ebipufa525@gmail.com'            => 8,
    'f3ze8cr9orpwc@yahoo.com'         => 9,
    'alinablohina00@list.ru'          => 10,
    'geitdaoot@yahoo.com'             => 11,
    'fqeicumjixdp@yahoo.com'          => 12,
    'judith3mills6017@gmail.com'      => 13,
    'noyoxiw389@kuandika.com'         => 14,
    'bks.tandukar@gmail.com'          => 15,
    'shekhar-test-bp-001@yopmail.com' => 16,
    'tandukar.bks@gmail.com'          => 17,
    'bikesh.iin@gmail.com'            => 18,
    'atithgrg@gmail.com'              => 19,
    'sundarthapa1986@gmail.com'       => 20,
    'bhab.peace@gmail.com'            => 21,
    'nirmala.malla1312@gmail.com'     => 22,
    'sirupant418@gmail.com'           => 23,
    'babypasa2024@gmail.com'          => 24,
    'shrijnacuty@gmail.com'           => 25,
    'anojamanandhar123@gmail.com'     => 26,
    'atifwashim2056@gmail.com'        => 27,
    'shraddhashrestha777@gmail.com'   => 28,
    'amimarai5@gmail.com'             => 29,
    'adhikarisusma922@gmail.com'      => 30,
    'prerana.basnet@gmail.com'        => 31,
    'anish.gautam@icloud.com'         => 32,
    'sangay.gyalmo@gmail.com'         => 33,
    'binitamg067@gmail.com'           => 34,
    'thyevilthinegod@gmail.com'       => 35,
    'kurmitbantawa@gmail.com'         => 36,
    'shakushre10@gmail.com'           => 37,
    'shresthasaujanya80@gmail.com'    => 38,
    'mailbanita@gmail.com'            => 39,
    'rabi.tajale17@gmail.com'         => 40,
    'bidhi@thehivecraft.com'          => 41,
    'shansahamal@gmail.com'           => 42,
    'kamles_101@hotmail.com'          => 43,
    'susma.pun2074@gmail.com'         => 44,
    'phulmayalamatamang07@gmail.com'  => 45,
    'willo.the.wishp@gmail.com'       => 46,
    'anitamanandhar77@gmail.com'      => 47,
    'd.ashishyadav1993@gmail.com'     => 48,
    'dinakhadka1@gmail.com'           => 49,
    'idrlk24@gmail.com'               => 50,
    'sanzukuikel18@gmail.com'         => 51,
    'a@babypasa.com'                  => 52,
    'prasannashrestha831@gmail.com'   => 53,
    'aryalsudip256@gmail.com'         => 54,
    'shreejankapali2016@gmail.com'    => 55,
    'ranjushrestha211@gmail.com'      => 56,
    'radhikakoirala88@gmail.com'      => 57,
    'sainjumanika@gmail.com'          => 58,
    'prerana@ramanassociates.com.np'  => 59,
    'nishakoirala195@gmail.com'       => 60,
    'durgalimbusunuwar2740@gmail.com' => 61,
    'sujatarai693@gmail.com'          => 62,
    'seasonmaharjan@gmail.com'        => 63,
    'nimakhendo100@gmail.com'         => 64,
    'shubhekrai@gmail.com'            => 65,
    'chamlingsas07@gmail.com'         => 66,
    'sujalmb885@gmail.com'            => 67,
    'maharjan0anish@gmail.com'        => 68,
    'sanjubab468@gmail.com'           => 69,
    'neeketbhandari@gmail.com'        => 70,
    'tandukar.roziee@gmail.com'       => 71,
    'umeshgauchan565@hotmail.com'     => 72,
    'binduthapa019@gmail.com'         => 73,
    'sanzu.maharjan087@gmail.com'     => 74,
    'richabastola@gmail.com'          => 75,
    'raveenkunwar@gmail.com'          => 76,
    'shital.katuwal9812@gmail.com'    => 77,
    'srijanathapa369@gmail.com'       => 78,
    'kabitamaharjan18@gmail.com'      => 79,
    'sunitalamatheeeng@gmail.com'     => 80,
    'kdkadrsarada@gmail.com'          => 81,
    'beebuzz600@gmail.com'            => 82,
    'karkishisham@gmail.com'          => 83,
    'goodfeelingbaby04@gmail.com'     => 84,
    'poudelsaru786@gmail.com'         => 85,
    'smasuren@gmail.com'              => 86,
    'pranitasangroula65@gmail.com'    => 87,
    'saritabhandari703@gmail.com'     => 88,
    'limburusha6@gmail.com'           => 89,
    'sabnamshrestha402@gmail.com'     => 90,
    'kundan@bibhutisolutions.com'     => 91,
    'shreeshabista@gmail.com'         => 92,
    'roji50480@gmail.com'             => 93,
    'amiy.gajmer@gmail.com'           => 94,
    'bivek.poudel703@gmail.com'       => 95,
    'sweetynpj2016@gmail.com'         => 96,
    'wesleykrki@gmail.com'            => 97,
    'sobincshrestha@gmail.com'        => 98,
    'psherpa801@yahoo.com'            => 99,
    'slawaju2021@gmail.com'           => 100,
    'minilandschoolnepal@gmail.com'   => 101,
    'perkp2000@gmail.com'             => 102,
    'sumeetagurung277@gmail.com'      => 103,
    'kritikakc67@gmail.com'           => 104,
    'anupamastha29@gmail.com'         => 105,
    'arjunmaharjan2013@gmail.com'     => 106,
    'rizen@kavya.edu.np'              => 107,
    'deoneha44@gmail.com'             => 108,
    'akitatuladhar@gmail.com'         => 109,
    'iuc.sht@gmail.com'               => 110,
    'mmc.anjana@gmail.com'            => 111,
    'ghising08@gmail.com'             => 112,
    'aerizonna@gmail.com'             => 113,
    'preranashakya3@gmail.com'        => 114,
    'rashiluitel1@gmail.com'          => 115,
    'prettymind151@gmail.com'         => 116,
    'sampang141@gmail.com'            => 117,
    'aaradhanasrp@gmail.com'          => 118,
    'sachukc056@gmail.com'            => 119,
    'bijayachoudhary14@gmail.com'     => 120,
    'ashmastha900@gmail.com'          => 121,
    'subedishristi157@gmail.com'      => 122,
    'junee.mdr@gmail.com'             => 123,
    'sujanacharya123@gmail.com'       => 124,
    'julangaire@gmail.com'            => 125,
    'rojeenapoudel1@gmail.com'        => 126,
    'jn9851220410@gmail.com'          => 127,
    'shresupesh@gmail.com'            => 128,
    'preestha787@gmail.com'           => 129,
    'shivanishrestha91@gmail.com'     => 130,
    'bhandarirenuka47@gmail.com'      => 131,
    'ranjanapokhrel90@gmail.com'      => 132,
    'drsaroj62@gmail.com'             => 133,
    'prativa.bhujel12@gmail.com'      => 134,
    'nisha.todi974@gmail.com'         => 135,
    'khoju.tinashrestha@gmail.com'    => 136,
    'rubinasingh134@gmail.com'        => 137,
    'shristi.shakya82@gmail.com'      => 138,
    'acharya.sandhya0101@gmail.com'   => 139,
    'jasmine.karanjit@gmail.com'      => 140,
    'aerulimbu@gmail.com'             => 141,
    'prasunrai@hotmail.com'           => 142,
    'khanalkiran288@gmail.com'        => 143,
    'aayushi.karanjit@gmail.com'      => 144,
    'shvetah24@gmail.com'             => 145,
    'preranamanandhar3@gmail.com'     => 146,
    'shikhagrg@gmail.com'             => 147,
    'ranjeettamang333@gmail.com'      => 148,
    'updhaya29@gmail.com'             => 149,
    'raiashika524@gmail.com'          => 150,
    'bkshkaphle@gmail.com'            => 151,
    'ashok@thehivecraft.com'          => 152,
    'vishnachetri79@gmail.com'        => 153,
];

// ─────────────────────────────────────────────────────────────
// DATA: Magento customer ID → telephone
// Source: customer_address_entity from 127_0_0_1.sql
// Where multiple addresses exist per customer, the most recent
// active address telephone is used (highest entity_id wins).
// 121 of 152 customers have a telephone value.
// ─────────────────────────────────────────────────────────────

$magento_id_to_phone = [
    5   => '9811223344',
    6   => '9813139449',
    7   => '0987654321',  // test account
    14  => '9813139449',
    15  => '9851032923',
    17  => '9846746348',
    18  => '9851325074',
    19  => '9851090464',
    20  => '9745949796',
    21  => '9851166429',
    22  => '9851157599',
    24  => '9841455018',
    25  => '986-1842971',
    26  => '9860280547',
    27  => '9805907070',
    28  => '9851325074',
    29  => '9761785645',
    30  => '9840269326',
    31  => '9843265281',
    32  => '9851348228',
    34  => '9825111265',
    35  => '9825310848',
    36  => '9804037665',
    40  => '9843159635',
    42  => '9841157515',
    43  => '9803192265',
    45  => '9840456955',
    46  => '9806342587',
    47  => '9865321014',
    48  => '9840321166',
    49  => '9841867190',
    50  => '9818668384',
    51  => '9869864966',
    54  => '9851087814',
    55  => '9843707669',
    56  => '9844645149',
    57  => '9858753299',
    58  => '9841753100',
    59  => '9843505775',
    60  => '9819046744',
    61  => '9804058182',
    62  => '9817970400',
    63  => '9841175602',
    64  => '9741670950',
    65  => '984236540',
    66  => '9800904668',
    67  => '9843802671',
    68  => '9849770547',
    69  => '9808354383',
    71  => '9802200209',
    72  => '9841308931',
    73  => '9846146502',
    74  => '9808246627',
    75  => '9846153088',
    76  => '9805812378',
    77  => '9802200209',
    78  => '9860661399',
    79  => '9860030252',
    80  => '9823395392',
    81  => '9842082401',
    82  => '9818014946',
    83  => '9841804540',
    84  => '9840018720',
    86  => '9841207179',
    87  => '9808631089',
    88  => '9866251114',
    89  => '9800901764',
    90  => '9841149794',
    92  => '9818370025',
    94  => '9824055767',
    95  => '9845194703',
    96  => '9801202959',
    98  => '9849809012',
    100 => '9709093067',
    101 => '9843355760',
    102 => '9841334879',
    103 => '9846848290',
    104 => '9849679696',
    105 => '9847199070',
    106 => '9841782804',
    107 => '9851223316',
    108 => '9810443919',
    109 => '9843504579',
    110 => '9861550955',
    111 => '9857651333',
    112 => '9745696827',
    114 => '9849302998',
    115 => '9861101063',
    116 => '9841567715',
    117 => '9851219499',
    118 => '9849406720',
    119 => '9841406851',
    120 => '9842405664',
    121 => '9860294676',
    122 => '9843438240',
    123 => '9841318926',
    124 => '9806608743',
    125 => '9857048735',
    127 => '9851220410',
    128 => '9841728199',
    129 => '9851361193',
    130 => '9841647007',
    131 => '9843807282',
    132 => '9843174439',
    133 => '9856057433',
    134 => '9804158294',
    136 => '9841348504',
    139 => '9846945933',
    140 => '9841662213',
    141 => '9812346776',
    142 => '9851225340',
    143 => '9868159112',
    144 => '9801110927',
    145 => '9768509331',
    146 => '9841834700',
    147 => '9863026506',
    148 => '9765009518',
    149 => '9810133448',
    150 => '9863717975',
    151 => '9852670198',
    153 => '9741802976',
];

// ─────────────────────────────────────────────────────────────
// MIGRATION
// ─────────────────────────────────────────────────────────────

$count_updated          = 0;  // fresh write — had no billing_phone before
$count_overridden       = 0;  // replaced a different existing billing_phone
$count_already_correct  = 0;  // existing value already matches Magento phone
$count_skipped_nomatch  = 0;  // email not in Magento data
$count_skipped_nophone  = 0;  // in Magento but no telephone recorded
$errors                 = [];

$wp_users = get_users( [
    'fields' => [ 'ID', 'user_email' ],
    'number' => -1,
] );

WP_CLI::log( 'WP users to process: ' . count( $wp_users ) );
WP_CLI::log( '' );

foreach ( $wp_users as $user ) {
    $email   = strtolower( trim( $user->user_email ) );
    $user_id = (int) $user->ID;

    // Match to Magento by email
    if ( ! isset( $magento_email_to_id[ $email ] ) ) {
        $count_skipped_nomatch++;
        continue;
    }

    $magento_id = $magento_email_to_id[ $email ];

    // Look up phone
    if ( ! isset( $magento_id_to_phone[ $magento_id ] ) ) {
        $count_skipped_nophone++;
        continue;
    }

    $phone = trim( $magento_id_to_phone[ $magento_id ] );

    if ( empty( $phone ) ) {
        $count_skipped_nophone++;
        continue;
    }

    // Existing value — used to label the action and to avoid a redundant write.
    $existing    = (string) get_user_meta( $user_id, 'billing_phone', true );
    $is_override = ( '' !== $existing );

    // Nothing to do when the stored value already matches the Magento phone.
    // (update_user_meta() also returns false in this no-op case, which would
    //  otherwise be misreported as a write failure below.)
    if ( $existing === $phone ) {
        $count_already_correct++;
        continue;
    }

    // Write — overwrites any existing value.
    if ( ! $dry_run ) {
        $result = update_user_meta( $user_id, 'billing_phone', $phone );
        if ( false === $result ) {
            $errors[] = "Failed: user_id={$user_id} ({$email})";
            continue;
        }
    }

    WP_CLI::log( sprintf(
        '%s  user_id=%-5d  %-42s  %s%s',
        $dry_run ? '[DRY-RUN]' : ( $is_override ? '[OVERRIDE]' : '[UPDATED]' ),
        $user_id,
        $email,
        $phone,
        $is_override ? '  (was: ' . $existing . ')' : ''
    ) );

    if ( $is_override ) {
        $count_overridden++;
    } else {
        $count_updated++;
    }
}

// ─────────────────────────────────────────────────────────────
// SUMMARY
// ─────────────────────────────────────────────────────────────

WP_CLI::log( '' );
WP_CLI::log( '─── Summary ───' );
WP_CLI::log( sprintf( '  %-38s %d', ( $dry_run ? 'Would write (was empty):' : 'Updated (was empty):' ),        $count_updated ) );
WP_CLI::log( sprintf( '  %-38s %d', ( $dry_run ? 'Would override (had a phone):' : 'Overridden (replaced existing):' ), $count_overridden ) );
WP_CLI::log( sprintf( '  %-38s %d', 'Skipped (already correct):',     $count_already_correct ) );
WP_CLI::log( sprintf( '  %-38s %d', 'Skipped (not in Magento data):', $count_skipped_nomatch ) );
WP_CLI::log( sprintf( '  %-38s %d', 'Skipped (no phone in Magento):', $count_skipped_nophone ) );

if ( ! empty( $errors ) ) {
    WP_CLI::log( '' );
    foreach ( $errors as $e ) {
        WP_CLI::warning( $e );
    }
}

WP_CLI::log( '' );

if ( $dry_run ) {
    WP_CLI::log( 'Dry run complete. Re-run without --dry-run to write changes.' );
} else {
    WP_CLI::success( 'Done. billing_phone backfilled from Magento data.' );
}
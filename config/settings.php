<?php
/**
 * Configuration générale du site de mariage.
 * Toutes les données « métier » (date, lieux, hébergements) sont regroupées ici
 * afin de pouvoir être modifiées sans toucher au reste du code.
 */

return [
    // ---------------------------------------------------------------------
    // Les mariés
    // ---------------------------------------------------------------------
    'couple' => [
        'bride'    => 'Julia',
        'groom'    => 'Jérémy',
        'initials' => 'I J', // cachet de cire
    ],

    // ---------------------------------------------------------------------
    // Le jour J (fuseau Europe/Paris — le 23/10/2027 est encore en heure d'été)
    // ---------------------------------------------------------------------
    'wedding_date'  => '2027-10-23T16:00:00+02:00',
    'rsvp_deadline' => '2027-08-23T23:59:59+02:00',

    // ---------------------------------------------------------------------
    // Langues
    // ---------------------------------------------------------------------
    'default_locale' => 'fr',
    'locales'        => ['fr' => 'Français', 'ro' => 'Română'],

    // ---------------------------------------------------------------------
    // Déroulement de la journée
    // ---------------------------------------------------------------------
    'schedule' => [
        [
            'id'    => 'mairie',
            'time'  => '16:00',
            'icon'  => 'rings',
            'map'   => 'https://www.google.com/maps/search/?api=1&query=Mairie+de+Saint-Leu-d%27Esserent',
        ],
        [
            'id'    => 'eglise',
            'time'  => '16:30',
            'icon'  => 'church',
            'map'   => 'https://www.google.com/maps/search/?api=1&query=Abbatiale+Saint-Nicolas+Saint-Leu-d%27Esserent',
        ],
        [
            'id'    => 'chateau',
            'time'  => '18:00',
            'icon'  => 'glasses',
            'map'   => 'https://www.google.com/maps/search/?api=1&query=Ch%C3%A2teau+de+Pontarm%C3%A9',
        ],
    ],

    // ---------------------------------------------------------------------
    // Hébergements autour du château de Pontarmé
    // ⚠️ Vérifier téléphones / tarifs avant la mise en ligne définitive.
    // ---------------------------------------------------------------------
    'accommodations' => [
        [
            'id'       => 'relais-aumale',
            'name'     => "Le Relais d'Aumale",
            'photo'    => 'assets/medias/hebergements/relais-aumale.svg',
            'address'  => "37 place des Fêtes, Montgrésin, 60560 Orry-la-Ville",
            'phone'    => '+33 3 44 54 61 31',
            'distance' => 4,
            'url'      => 'https://www.relais-aumale.fr/',
        ],
        [
            'id'       => 'mont-royal',
            'name'     => 'Tiara Château Hôtel Mont Royal Chantilly',
            'photo'    => 'assets/medias/hebergements/mont-royal.svg',
            'address'  => 'Route de Plailly, 60520 La Chapelle-en-Serval',
            'phone'    => '+33 3 44 54 50 50',
            'distance' => 5,
            'url'      => 'https://www.tiara-hotels.com/fr/hotels/tiara-chateau-hotel-mont-royal-chantilly',
        ],
        [
            'id'       => 'montvillargenne',
            'name'     => 'Château de Montvillargenne',
            'photo'    => 'assets/medias/hebergements/montvillargenne.svg',
            'address'  => '6 avenue François Mathet, 60270 Gouvieux',
            'phone'    => '+33 3 44 62 37 37',
            'distance' => 13,
            'url'      => 'https://www.chateaudemontvillargenne.com/',
        ],
        [
            'id'       => 'hostellerie-lys',
            'name'     => 'Hostellerie du Lys',
            'photo'    => 'assets/medias/hebergements/hostellerie-lys.svg',
            'address'  => "63 7ème avenue, Lys-Chantilly, 60260 Lamorlaye",
            'phone'    => '+33 3 44 21 26 19',
            'distance' => 9,
            'url'      => 'https://www.hostellerie-du-lys.com/',
        ],
        [
            'id'       => 'ibis-senlis',
            'name'     => 'ibis Senlis',
            'photo'    => 'assets/medias/hebergements/ibis-senlis.svg',
            'address'  => 'Route Nationale 324, 60300 Senlis',
            'phone'    => '+33 3 44 53 70 50',
            'distance' => 11,
            'url'      => 'https://all.accor.com/hotel/0344/index.fr.shtml',
        ],
        [
            'id'       => 'airbnb',
            'name'     => 'Airbnb & chambres d’hôtes',
            'photo'    => 'assets/medias/hebergements/airbnb.svg',
            'address'  => 'Pontarmé, Senlis, Chantilly et alentours',
            'phone'    => null,
            'distance' => null,
            'url'      => 'https://www.airbnb.fr/s/Pontarm%C3%A9--France/homes',
        ],
    ],

    // ---------------------------------------------------------------------
    // Notifications
    // ---------------------------------------------------------------------
    'mail' => [
        'to'        => ['juliapohrib@yahoo.fr', 'jeremy.spaeth@ik.me'],
        'from'      => 'no-reply@julia-et-jeremy.fr',
        'from_name' => 'Julia & Jérémy — Mariage',
    ],

    // Liste des invités (une ligne par personne) et stockage des réponses,
    // tous deux hors document root.
    'guest_list'  => __DIR__ . '/invites.csv',
    'storage_dir' => dirname(__DIR__) . '/var/rsvp',
];

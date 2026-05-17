<?php

return [
    // Stages
    'stages' => [
        'pending_processing' => 'En attente de traitement',
        'assigned_admin' => 'Assignée à un admin',
        'call_scheduled' => 'Call programmé',
        'custom_offer_created' => 'Offre personnalisée créée',
        'closed' => 'Clôturée',
    ],

    // Status
    'status' => [
        'open' => 'Ouverte',
        'closed' => 'Clôturée',
    ],

    // Outcomes
    'outcomes' => [
        'won' => 'Gagnée',
        'lost' => 'Perdue',
    ],

    // Offer statuses
    'offer_status' => [
        'draft' => 'Brouillon',
        'sent' => 'Envoyée',
        'accepted' => 'Acceptée',
        'rejected' => 'Refusée',
        'expired' => 'Expirée',
    ],

    // Activity types
    'activities' => [
        'created' => 'Demande enregistrée',
        'stage_changed' => 'Étape mise à jour',
        'assigned' => 'Demande assignée',
        'note_added' => 'Note interne ajoutée',
        'call_scheduled' => 'Call planifié',
        'outcome_updated' => 'Issue commerciale mise à jour',
        'offer_created' => 'Offre créée',
        'offer_sent' => 'Offre envoyée',
        'offer_responded' => 'Réponse client reçue',
        'offer_deleted' => 'Offre supprimée',
    ],

    // Messages
    'messages' => [
        'request_created' => 'Demande de devis envoyée avec succès.',
        'stage_updated' => 'Étape mise à jour.',
        'assignment_updated' => 'Assignation mise à jour.',
        'note_added' => 'Note ajoutée.',
        'call_scheduled' => 'Call planifié.',
        'outcome_updated' => 'Issue mise à jour.',
        'offer_created' => 'Offre créée en brouillon.',
        'offer_updated' => 'Offre mise à jour.',
        'offer_sent' => 'Offre envoyée au client.',
        'offer_deleted' => 'Offre supprimée.',
        'offer_expired' => 'Cette offre a expiré.',
        'only_draft_editable' => 'Seules les offres en brouillon peuvent être modifiées.',
        'only_draft_sendable' => 'Seules les offres en brouillon peuvent être envoyées.',
        'only_draft_deletable' => 'Seules les offres en brouillon peuvent être supprimées.',
        'admin_required' => 'Le collaborateur assigné doit être administrateur.',
    ],

    // Notifications
    'notifications' => [
        'new_request_subject' => 'Nouvelle demande de devis Business',
        'offer_ready_subject' => 'Votre offre personnalisée est prête',
        'offer_responded_subject' => 'Offre :status — :tracking_code',
        'call_scheduled_subject' => 'Votre call Business est planifié',
        'request_updated_subject' => 'Mise à jour de votre demande Business',
    ],
];

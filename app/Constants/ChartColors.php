<?php

namespace App\Constants;

/**
 * Palette de couleurs hex utilisée pour les graphiques du dashboard
 * et la coloration sémantique des entités (plans, types d'événement, etc.).
 *
 * Centralisé pour éviter la duplication des littéraux et garantir
 * la cohérence visuelle entre les widgets.
 */
final class ChartColors
{
    // Sémantique
    public const SUCCESS = '#10B981';   // vert — paid, completed, accepted
    public const DANGER  = '#EF4444';   // rouge — refunded, declined, à faire, dépensé
    public const WARNING = '#F59E0B';   // orange — pending, en cours
    public const WARNING_ALT = '#F97316'; // orange foncé — en cours (variant)
    public const PRIMARY = '#4F46E5';   // indigo — à venir, nouveaux, essai
    public const NEUTRAL = '#6B7280';   // gris — inactifs, fallback
    public const NEUTRAL_LIGHT = '#6b7280'; // alias historique en minuscules

    // Catégoriel
    public const VIOLET       = '#7C3AED';
    public const VIOLET_LIGHT = '#8B5CF6';
    public const CYAN         = '#06B6D4';
    public const PINK         = '#EC4899';
    public const MAGENTA      = '#E91E8C';

    /**
     * Mapping plan slug → couleur.
     *
     * @return array<string, string>
     */
    public static function planColors(): array
    {
        return [
            'essai-gratuit' => self::PRIMARY,
            'pro'           => self::SUCCESS,
            'agence'        => self::VIOLET,
        ];
    }

    /**
     * Mapping type d'événement → couleur.
     *
     * @return array<string, string>
     */
    public static function eventTypeColors(): array
    {
        return [
            'mariage'      => self::MAGENTA,
            'anniversaire' => self::PRIMARY,
            'conférence'   => self::WARNING,
            'fête privée'  => self::SUCCESS,
            'séminaire'    => self::VIOLET_LIGHT,
            'baptême'      => self::CYAN,
            'gala'         => self::PINK,
            'baby_shower'  => self::SUCCESS,
            'soiree'       => self::WARNING,
            'brunch'       => self::VIOLET_LIGHT,
            'autre'        => self::NEUTRAL,
        ];
    }
}

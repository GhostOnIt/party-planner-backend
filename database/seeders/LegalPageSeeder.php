<?php

namespace Database\Seeders;

use App\Models\LegalPage;
use Illuminate\Database\Seeder;

class LegalPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $legalPages = [
            [
                'slug' => 'terms',
                'title' => 'Conditions Générales d\'Utilisation',
                'content' => $this->getTermsContent(),
                'is_published' => true,
            ],
            [
                'slug' => 'privacy',
                'title' => 'Politique de Confidentialité',
                'content' => $this->getPrivacyContent(),
                'is_published' => true,
            ],
        ];

        foreach ($legalPages as $page) {
            LegalPage::updateOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }
    }

    private function getTermsContent(): string
    {
        return <<<'HTML'
<h2>1. Objet</h2>
<p>Les présentes Conditions Générales d'Utilisation (CGU) régissent l'accès et l'utilisation de la plateforme Party Planner (ci-après "le Service").</p>

<h2>2. Acceptation des conditions</h2>
<p>En accédant au Service, vous acceptez d'être lié par les présentes CGU. Si vous n'acceptez pas ces conditions, veuillez ne pas utiliser le Service.</p>

<h2>3. Description du Service</h2>
<p>Party Planner est une plateforme de gestion et d'organisation d'événements permettant aux utilisateurs de :</p>
<ul>
    <li>Créer et gérer des événements</li>
    <li>Gérer les invités et les collaborateurs</li>
    <li>Suivre les budgets et les tâches</li>
    <li>Partager des photos et des documents</li>
</ul>

<h2>4. Inscription et compte utilisateur</h2>
<p>Pour utiliser le Service, vous devez créer un compte en fournissant des informations exactes et à jour. Vous êtes responsable de la confidentialité de vos identifiants de connexion.</p>

<h2>5. Utilisation acceptable</h2>
<p>Vous vous engagez à utiliser le Service de manière légale et respectueuse. Il est interdit de :</p>
<ul>
    <li>Violer les droits d'autrui</li>
    <li>Diffuser du contenu illégal ou offensant</li>
    <li>Tenter de compromettre la sécurité du Service</li>
    <li>Utiliser le Service à des fins commerciales non autorisées</li>
</ul>

<h2>6. Propriété intellectuelle</h2>
<p>Le contenu du Service, y compris les textes, graphiques, logos et logiciels, est protégé par les droits de propriété intellectuelle.</p>

<h2>7. Limitation de responsabilité</h2>
<p>Le Service est fourni "tel quel". Nous ne garantissons pas l'absence d'interruptions ou d'erreurs dans le fonctionnement du Service.</p>

<h2>8. Modifications des CGU</h2>
<p>Nous nous réservons le droit de modifier les présentes CGU à tout moment. Les modifications entreront en vigueur dès leur publication.</p>

<h2>9. Contact</h2>
<p>Pour toute question concernant ces CGU, veuillez nous contacter à l'adresse indiquée sur notre site.</p>
HTML;
    }

    private function getPrivacyContent(): string
    {
        return <<<'HTML'
<h2>1. Introduction</h2>
<p>La présente Politique de Confidentialité décrit comment Party Planner collecte, utilise et protège vos données personnelles conformément au Règlement Général sur la Protection des Données (RGPD).</p>

<h2>2. Données collectées</h2>
<p>Nous collectons les types de données suivants :</p>
<ul>
    <li><strong>Données d'identification :</strong> nom, prénom, adresse email, numéro de téléphone</li>
    <li><strong>Données de connexion :</strong> adresse IP, type de navigateur, pages visitées</li>
    <li><strong>Données d'événements :</strong> informations sur les événements que vous créez ou auxquels vous participez</li>
    <li><strong>Contenu utilisateur :</strong> photos, commentaires et autres contenus que vous partagez</li>
</ul>

<h2>3. Finalités du traitement</h2>
<p>Vos données sont utilisées pour :</p>
<ul>
    <li>Fournir et améliorer nos services</li>
    <li>Gérer votre compte utilisateur</li>
    <li>Vous envoyer des communications relatives au Service</li>
    <li>Assurer la sécurité de la plateforme</li>
    <li>Respecter nos obligations légales</li>
</ul>

<h2>4. Base légale du traitement</h2>
<p>Le traitement de vos données repose sur :</p>
<ul>
    <li>L'exécution du contrat (fourniture du Service)</li>
    <li>Votre consentement (communications marketing)</li>
    <li>Notre intérêt légitime (amélioration du Service, sécurité)</li>
    <li>Le respect d'obligations légales</li>
</ul>

<h2>5. Conservation des données</h2>
<p>Vos données sont conservées pendant la durée de votre utilisation du Service, puis archivées conformément aux obligations légales applicables.</p>

<h2>6. Partage des données</h2>
<p>Vos données peuvent être partagées avec :</p>
<ul>
    <li>Les autres utilisateurs (selon vos paramètres de partage)</li>
    <li>Nos prestataires techniques (hébergement, email)</li>
    <li>Les autorités compétentes (sur demande légale)</li>
</ul>

<h2>7. Vos droits</h2>
<p>Conformément au RGPD, vous disposez des droits suivants :</p>
<ul>
    <li><strong>Droit d'accès :</strong> obtenir une copie de vos données</li>
    <li><strong>Droit de rectification :</strong> corriger vos données inexactes</li>
    <li><strong>Droit à l'effacement :</strong> demander la suppression de vos données</li>
    <li><strong>Droit à la portabilité :</strong> recevoir vos données dans un format structuré</li>
    <li><strong>Droit d'opposition :</strong> vous opposer à certains traitements</li>
    <li><strong>Droit de limitation :</strong> limiter le traitement de vos données</li>
</ul>

<h2>8. Sécurité</h2>
<p>Nous mettons en œuvre des mesures techniques et organisationnelles appropriées pour protéger vos données contre tout accès non autorisé, modification, divulgation ou destruction.</p>

<h2>9. Cookies</h2>
<p>Notre site utilise des cookies pour améliorer votre expérience. Vous pouvez gérer vos préférences de cookies dans les paramètres de votre navigateur.</p>

<h2>10. Modifications</h2>
<p>Cette politique peut être mise à jour périodiquement. Nous vous informerons de tout changement significatif.</p>

<h2>11. Contact</h2>
<p>Pour exercer vos droits ou pour toute question relative à cette politique, contactez notre Délégué à la Protection des Données à l'adresse indiquée sur notre site.</p>
HTML;
    }
}

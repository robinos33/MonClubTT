<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_Comite;
use PHPUnit\Framework\TestCase;

/**
 * Tests des règles du comité directeur : nom affiché, initiales, libellé des
 * commissions et répartition entre le bureau et les autres membres
 * ([monclubtt_comite]).
 */
final class ComiteTest extends TestCase
{
    private function commission(int $id, string $nom, string $slug, int $ordre): array
    {
        return array('id' => $id, 'nom' => $nom, 'slug' => $slug, 'ordre' => $ordre);
    }

    private function membre(string $prenom, string $nom, int $ordre, string $poste = '', array $commissions = array()): array
    {
        return array('id' => 0, 'prenom' => $prenom, 'nom' => $nom, 'ordre' => $ordre, 'photoId' => 0, 'photo' => '', 'posteBureau' => $poste, 'commissions' => $commissions);
    }

    public function test_should_join_first_and_last_name_without_extra_spaces(): void
    {
        $this->assertSame('Marie Dupont', MonClubTT_Comite::nomComplet('  Marie ', ' Dupont  '));
        $this->assertSame('Jean-Luc Le Goff', MonClubTT_Comite::nomComplet('Jean-Luc', "Le \t Goff"));
        $this->assertSame('Marie', MonClubTT_Comite::nomComplet('Marie', ''));
    }

    public function test_should_build_uppercase_initials_with_accents(): void
    {
        $this->assertSame('ÉD', MonClubTT_Comite::initiales('élodie', 'dupont'));
        $this->assertSame('M', MonClubTT_Comite::initiales('Marie', ''));
        $this->assertSame('', MonClubTT_Comite::initiales('', ''));
    }

    public function test_should_clean_bureau_poste(): void
    {
        $this->assertSame('Trésorier adjoint', MonClubTT_Comite::poste("  Trésorier <b>adjoint</b> \n"));
    }

    public function test_should_label_commissions_without_repeating_prefix(): void
    {
        $this->assertSame('Commission Communication', MonClubTT_Comite::libelleCommissions(array('Communication')));
        $this->assertSame('Commission Communication / Équipes', MonClubTT_Comite::libelleCommissions(array('Commission Communication', 'Équipes')));
        $this->assertSame('Commission sportive / Matériel', MonClubTT_Comite::libelleCommissions(array('Commission sportive', 'Commissions Matériel', ' ')));
        $this->assertSame('', MonClubTT_Comite::libelleCommissions(array()));
    }

    public function test_should_split_bureau_and_other_members(): void
    {
        $communication = $this->commission(1, 'Communication', 'communication', 2);
        $equipes       = $this->commission(2, 'Équipes', 'equipes', 1);
        $membres = array(
            $this->membre('Julien', 'Berho', 3, 'Trésorier'),
            $this->membre('Robin', 'Aldasoro', 0, '', array($communication, $equipes)),
            $this->membre('William', 'Garcia', 1, 'Président', array($communication)),
            $this->membre('Arnaud', 'Brun', 0, '', array($equipes)),
            $this->membre('Philippe', 'Volkaert', 0, '  '),
        );

        $parties = MonClubTT_Comite::repartir($membres);

        $this->assertSame(array('William', 'Julien'), array_column($parties['bureau'], 'prenom'));
        // Même ordre : par nom (Aldasoro, Brun, Volkaert).
        $this->assertSame(array('Robin', 'Arnaud', 'Philippe'), array_column($parties['comite'], 'prenom'));
        // Commissions triées par leur ordre.
        $this->assertSame('Commission Équipes / Communication', $parties['comite'][0]['libelleCommissions']);
        $this->assertSame('', $parties['comite'][2]['libelleCommissions']);
    }

    public function test_should_keep_only_members_of_filtered_commissions(): void
    {
        $communication = $this->commission(1, 'Communication', 'communication', 1);
        $materiel      = $this->commission(2, 'Matériel', 'materiel', 2);
        $membres = array(
            $this->membre('William', 'Garcia', 1, 'Président', array($communication)),
            $this->membre('Robin', 'Aldasoro', 0, '', array($communication)),
            $this->membre('Philippe', 'Volkaert', 0, '', array($materiel)),
        );

        $parties = MonClubTT_Comite::repartir($membres, array(' Communication '));

        $this->assertSame(array('William'), array_column($parties['bureau'], 'prenom'));
        $this->assertSame(array('Robin'), array_column($parties['comite'], 'prenom'));
    }

    public function test_should_return_empty_parts_without_members(): void
    {
        $this->assertSame(array('bureau' => array(), 'comite' => array()), MonClubTT_Comite::repartir(array()));
    }
}

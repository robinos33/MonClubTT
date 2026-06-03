# Guide TDD - Test-Driven Development

## Philosophie

**"Red, Green, Refactor"** : L'approche TDD suit un cycle strict en 3 phases.

## Le cycle TDD

```
┌─────────────────────────────────────────────┐
│                                             │
│  1. RED   ──→  2. GREEN  ──→  3. REFACTOR  │
│     ↑                               │       │
│     └───────────────────────────────┘       │
│                                             │
└─────────────────────────────────────────────┘
```

### Phase 1 : RED (Écrire un test qui échoue)

**Objectif** : Définir clairement le comportement attendu

**Actions** :
1. Identifier le comportement à implémenter
2. Écrire un test qui décrit ce comportement
3. Exécuter le test et vérifier qu'il **échoue**
4. Analyser le message d'erreur

**Exemple** :

```php
<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit\Domain\ValueObject;

use MonClubTT\Domain\ValueObject\Licence;
use MonClubTT\Domain\Exception\InvalidLicenceException;
use PHPUnit\Framework\TestCase;

final class LicenceTest extends TestCase
{
    // ✅ Phase RED : Ce test échoue car la classe n'existe pas encore
    public function test_should_create_licence_when_format_valid(): void
    {
        // Arrange
        $numeroLicence = '1234567';

        // Act
        $licence = new Licence($numeroLicence);

        // Assert
        $this->assertEquals('1234567', $licence->getValue());
    }
}
```

**Exécution** :
```bash
$ composer test

PHPUnit 11.0.0

E                                                                   1 / 1 (100%)

Time: 00:00.050, Memory: 6.00 MB

There was 1 error:

1) LicenceTest::test_should_create_licence_when_format_valid
Error: Class "MonClubTT\Domain\ValueObject\Licence" not found

ERRORS!
Tests: 1, Assertions: 0, Errors: 1.
```

**✓ Attendu** : Le test échoue car le code n'existe pas

### Phase 2 : GREEN (Faire passer le test)

**Objectif** : Écrire le code **minimal** pour faire passer le test

**Actions** :
1. Créer la classe/méthode nécessaire
2. Implémenter le comportement minimum
3. Exécuter le test et vérifier qu'il **passe**
4. Ne PAS sur-engineer

**Exemple** :

```php
<?php

declare(strict_types=1);

namespace MonClubTT\Domain\ValueObject;

final readonly class Licence
{
    public function __construct(private string $value)
    {
        // Pas de validation pour l'instant (minimum pour faire passer le test)
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
```

**Exécution** :
```bash
$ composer test

PHPUnit 11.0.0

.                                                                   1 / 1 (100%)

Time: 00:00.012, Memory: 6.00 MB

OK (1 test, 1 assertion)
```

**✓ Attendu** : Le test passe

### Ajouter les tests de validation (RED)

```php
public function test_should_throw_exception_when_licence_too_short(): void
{
    // Arrange
    $licenceInvalide = '123';

    // Act & Assert
    $this->expectException(InvalidLicenceException::class);
    $this->expectExceptionMessage('Une licence doit contenir exactement 7 chiffres');

    new Licence($licenceInvalide);
}

public function test_should_throw_exception_when_licence_contains_letters(): void
{
    // Arrange
    $licenceInvalide = '12ABC67';

    // Act & Assert
    $this->expectException(InvalidLicenceException::class);

    new Licence($licenceInvalide);
}
```

**Exécution** :
```bash
$ composer test

FF                                                                  2 / 2 (100%)

FAILURES!
Tests: 2, Assertions: 0, Failures: 2.
```

**✓ Attendu** : Les tests échouent (pas de validation)

### Ajouter la validation (GREEN)

```php
<?php

declare(strict_types=1);

namespace MonClubTT\Domain\ValueObject;

use MonClubTT\Domain\Exception\InvalidLicenceException;

final readonly class Licence
{
    public function __construct(private string $value)
    {
        if (!preg_match('/^\d{7}$/', $value)) {
            throw new InvalidLicenceException(
                'Une licence doit contenir exactement 7 chiffres'
            );
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
```

**Exécution** :
```bash
$ composer test

...                                                                 3 / 3 (100%)

OK (3 tests, 3 assertions)
```

**✓ Attendu** : Tous les tests passent

### Phase 3 : REFACTOR (Améliorer le code)

**Objectif** : Nettoyer le code tout en gardant les tests verts

**Actions** :
1. Identifier les duplications
2. Améliorer la lisibilité
3. Appliquer les principes SOLID
4. Exécuter les tests après **chaque** modification

**Exemple de refactoring** :

```php
<?php

declare(strict_types=1);

namespace MonClubTT\Domain\ValueObject;

use MonClubTT\Domain\Exception\InvalidLicenceException;

/**
 * Représente un numéro de licence FFTT
 *
 * Value Object immuable représentant une licence de joueur.
 * Une licence FFTT est composée de exactement 7 chiffres.
 *
 * @package MonClubTT\Domain\ValueObject
 */
final readonly class Licence
{
    private const PATTERN = '/^\d{7}$/';
    private const LONGUEUR_ATTENDUE = 7;

    /**
     * Crée une nouvelle instance de Licence
     *
     * @param string $value Le numéro de licence (7 chiffres)
     *
     * @throws InvalidLicenceException Si le format est invalide
     */
    public function __construct(private string $value)
    {
        $this->valider();
    }

    /**
     * Retourne la valeur de la licence
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Compare deux licences
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Valide le format de la licence
     *
     * @throws InvalidLicenceException
     */
    private function valider(): void
    {
        if (!preg_match(self::PATTERN, $this->value)) {
            throw new InvalidLicenceException(
                sprintf(
                    'Une licence doit contenir exactement %d chiffres. Reçu: "%s"',
                    self::LONGUEUR_ATTENDUE,
                    $this->value
                )
            );
        }
    }
}
```

**Test d'égalité** :
```php
public function test_should_be_equal_when_same_value(): void
{
    // Arrange
    $licence1 = new Licence('1234567');
    $licence2 = new Licence('1234567');

    // Act & Assert
    $this->assertTrue($licence1->equals($licence2));
}

public function test_should_not_be_equal_when_different_value(): void
{
    // Arrange
    $licence1 = new Licence('1234567');
    $licence2 = new Licence('7654321');

    // Act & Assert
    $this->assertFalse($licence1->equals($licence2));
}
```

**Exécution** :
```bash
$ composer test

.....                                                               5 / 5 (100%)

OK (5 tests, 5 assertions)
```

**✓ Attendu** : Tous les tests passent toujours après refactoring

## Structure des tests (AAA Pattern)

### Arrange-Act-Assert

Chaque test suit la structure AAA :

```php
public function test_should_do_something_when_condition(): void
{
    // ─────────────────────────────────
    // ARRANGE - Préparer les données
    // ─────────────────────────────────
    $licence = new Licence('1234567');
    $nom = new Nom('Dupont');
    $classement = new Classement(1000);

    // ─────────────────────────────────
    // ACT - Exécuter l'action testée
    // ─────────────────────────────────
    $joueur = new Joueur(
        JoueurId::generate(),
        $licence,
        $nom,
        $classement
    );

    // ─────────────────────────────────
    // ASSERT - Vérifier le résultat
    // ─────────────────────────────────
    $this->assertTrue($joueur->getLicence()->equals($licence));
    $this->assertEquals('Dupont', $joueur->getNom()->getValue());
    $this->assertEquals(1000, $joueur->getClassement()->getPoints());
}
```

## Nomenclature des tests

### Format recommandé

```php
// Format : test_should_[résultat]_when_[condition]
test_should_create_licence_when_format_valid()
test_should_throw_exception_when_licence_invalid()
test_should_return_zero_when_no_matches()
test_should_calculate_percentage_when_has_victories()
```

### Cas spéciaux

```php
// Tests de comportement métier
test_joueur_should_progress_classement_after_victory()
test_equipe_should_not_allow_duplicate_players()

// Tests de validation
test_classement_cannot_be_negative()
test_licence_must_contain_seven_digits()

// Tests d'égalité (Value Objects)
test_licences_are_equal_when_same_value()
test_classements_are_not_equal_when_different_points()
```

## Types de tests

### 1. Tests de Value Objects

**Checklist complète** :
- [ ] Création avec données valides
- [ ] Validation (tous les cas invalides)
- [ ] Égalité par valeur
- [ ] Immutabilité
- [ ] Cas limites

**Exemple complet** :

```php
final class ClassementTest extends TestCase
{
    // Création valide
    public function test_should_create_classement_with_valid_points(): void
    {
        $classement = new Classement(1000);
        $this->assertEquals(1000, $classement->getPoints());
    }

    // Validation - points négatifs
    public function test_should_throw_exception_when_points_negative(): void
    {
        $this->expectException(InvalidClassementException::class);
        new Classement(-100);
    }

    // Validation - points trop élevés
    public function test_should_throw_exception_when_points_exceed_maximum(): void
    {
        $this->expectException(InvalidClassementException::class);
        new Classement(4000);
    }

    // Cas limites
    public function test_should_accept_minimum_valid_points(): void
    {
        $classement = new Classement(0);
        $this->assertEquals(0, $classement->getPoints());
    }

    public function test_should_accept_maximum_valid_points(): void
    {
        $classement = new Classement(3500);
        $this->assertEquals(3500, $classement->getPoints());
    }

    // Égalité
    public function test_should_be_equal_when_same_points(): void
    {
        $classement1 = new Classement(1200);
        $classement2 = new Classement(1200);

        $this->assertTrue($classement1->equals($classement2));
    }

    // Immutabilité
    public function test_should_return_new_instance_when_adding_points(): void
    {
        $original = new Classement(1000);
        $nouveau = $original->ajouter(new Points(50));

        $this->assertEquals(1000, $original->getPoints());
        $this->assertEquals(1050, $nouveau->getPoints());
        $this->assertNotSame($original, $nouveau);
    }
}
```

### 2. Tests d'Entités

**Checklist complète** :
- [ ] Création et initialisation
- [ ] Comportements métier
- [ ] Protection des invariants
- [ ] Événements du domaine
- [ ] Identité

**Exemple complet** :

```php
final class JoueurTest extends TestCase
{
    // Création
    public function test_should_create_joueur_with_all_required_data(): void
    {
        // Arrange
        $id = JoueurId::generate();
        $licence = new Licence('1234567');
        $nom = new Nom('Dupont');
        $classement = new Classement(1000);

        // Act
        $joueur = new Joueur($id, $licence, $nom, $classement);

        // Assert
        $this->assertTrue($joueur->getId()->equals($id));
        $this->assertTrue($joueur->getLicence()->equals($licence));
    }

    // Comportement métier
    public function test_should_increase_classement_when_progressing(): void
    {
        // Arrange
        $joueur = $this->createJoueurWithClassement(1000);

        // Act
        $joueur->progresser(new Points(50));

        // Assert
        $this->assertEquals(1050, $joueur->getClassement()->getPoints());
    }

    // Invariants
    public function test_should_protect_minimum_classement(): void
    {
        // Arrange
        $joueur = $this->createJoueurWithClassement(100);

        // Act & Assert
        $this->expectException(InvalidClassementException::class);
        $joueur->regresser(new Points(200));  // Ne peut pas descendre en dessous de 0
    }

    // Événements du domaine
    public function test_should_raise_event_when_classement_modified(): void
    {
        // Arrange
        $joueur = $this->createJoueurWithClassement(1000);

        // Act
        $joueur->progresser(new Points(50));

        // Assert
        $events = $joueur->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ClassementModifie::class, $events[0]);
    }

    // Identité
    public function test_should_identify_by_id_not_by_attributes(): void
    {
        // Arrange
        $id = JoueurId::generate();
        $joueur1 = new Joueur($id, new Licence('1234567'), new Nom('Dupont'), new Classement(1000));
        $joueur2 = new Joueur($id, new Licence('7654321'), new Nom('Martin'), new Classement(1500));

        // Act & Assert
        $this->assertTrue($joueur1->getId()->equals($joueur2->getId()));
    }

    // Helper
    private function createJoueurWithClassement(int $points): Joueur
    {
        return new Joueur(
            JoueurId::generate(),
            new Licence('1234567'),
            new Nom('Test'),
            new Classement($points)
        );
    }
}
```

### 3. Tests de Use Cases

**Utiliser des mocks pour les dépendances** :

```php
final class CreerJoueurUseCaseTest extends TestCase
{
    private JoueurRepositoryInterface $repository;
    private CreerJoueurUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(JoueurRepositoryInterface::class);
        $this->useCase = new CreerJoueurUseCase($this->repository);
    }

    public function test_should_create_and_save_joueur_when_data_valid(): void
    {
        // Arrange
        $command = new CreerJoueurCommand(
            licence: '1234567',
            nom: 'Dupont',
            pointsClassement: 1000
        );

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Joueur $joueur) {
                return $joueur->getLicence()->getValue() === '1234567'
                    && $joueur->getNom()->getValue() === 'Dupont';
            }));

        // Act
        $joueurId = $this->useCase->execute($command);

        // Assert
        $this->assertInstanceOf(JoueurId::class, $joueurId);
    }

    public function test_should_throw_exception_when_licence_already_exists(): void
    {
        // Arrange
        $command = new CreerJoueurCommand(
            licence: '1234567',
            nom: 'Dupont',
            pointsClassement: 1000
        );

        $joueurExistant = $this->createMock(Joueur::class);

        $this->repository
            ->expects($this->once())
            ->method('findByLicence')
            ->willReturn($joueurExistant);

        // Act & Assert
        $this->expectException(LicenceDejaUtiliseeException::class);
        $this->useCase->execute($command);
    }
}
```

## Couverture de code

### Objectif : > 80%

```bash
$ composer test:coverage

Code Coverage Report:
  2026-01-08 10:30:00

 Summary:
  Classes: 95.00% (19/20)
  Methods: 92.31% (72/78)
  Lines:   88.24% (450/510)

Domain\Model:
  Joueur.php                100.00%
  Equipe.php                 95.00%

Domain\ValueObject:
  Licence.php               100.00%
  Classement.php            100.00%
```

### Zones à couvrir prioritairement

1. **Domain Layer** : 100% (logique métier critique)
2. **Application Layer** : > 90% (cas d'usage)
3. **Infrastructure Layer** : > 70% (code technique)

## Checklist avant commit

```bash
# 1. Tous les tests passent
✓ composer test

# 2. Couverture suffisante
✓ composer test:coverage

# 3. Analyse statique
✓ composer analyse

# 4. Standards de code
✓ composer cs:check

# OU tout en une commande
✓ composer quality
```

## Exemples de workflow complet

### Exemple : Ajouter la méthode calculerTauxVictoire()

#### Étape 1 : RED

```php
// tests/Unit/Domain/Model/JoueurTest.php

public function test_should_calculate_win_rate_when_has_matches(): void
{
    // Arrange
    $joueur = $this->createJoueur();
    $joueur->ajouterResultat(new ResultatPartie(victoire: true));
    $joueur->ajouterResultat(new ResultatPartie(victoire: true));
    $joueur->ajouterResultat(new ResultatPartie(victoire: false));

    // Act
    $tauxVictoire = $joueur->calculerTauxVictoire();

    // Assert
    $this->assertEquals(66.67, $tauxVictoire);  // 2/3 = 66.67%
}

public function test_should_return_zero_when_no_matches(): void
{
    // Arrange
    $joueur = $this->createJoueur();

    // Act
    $tauxVictoire = $joueur->calculerTauxVictoire();

    // Assert
    $this->assertEquals(0.0, $tauxVictoire);
}
```

```bash
$ composer test

FF                                                                  2 / 2

FAILURES!
Tests: 2, Assertions: 0, Failures: 2.
Error: Call to undefined method Joueur::calculerTauxVictoire()
```

#### Étape 2 : GREEN

```php
// src/Domain/Model/Joueur.php

final class Joueur
{
    private array $resultats = [];

    public function ajouterResultat(ResultatPartie $resultat): void
    {
        $this->resultats[] = $resultat;
    }

    public function calculerTauxVictoire(): float
    {
        if (count($this->resultats) === 0) {
            return 0.0;
        }

        $victoires = array_filter(
            $this->resultats,
            fn(ResultatPartie $r) => $r->estVictoire()
        );

        return round(
            (count($victoires) / count($this->resultats)) * 100,
            2
        );
    }
}
```

```bash
$ composer test

..                                                                  2 / 2

OK (2 tests, 2 assertions)
```

#### Étape 3 : REFACTOR

```php
// src/Domain/Model/Joueur.php

final class Joueur
{
    /** @var array<ResultatPartie> */
    private array $resultats = [];

    public function ajouterResultat(ResultatPartie $resultat): void
    {
        $this->resultats[] = $resultat;
    }

    /**
     * Calcule le taux de victoire du joueur en pourcentage
     *
     * @return float Pourcentage de victoires (0.0 à 100.0)
     */
    public function calculerTauxVictoire(): float
    {
        $nombrePartiesJouees = $this->getNombrePartiesJouees();

        if ($nombrePartiesJouees === 0) {
            return 0.0;
        }

        $nombreVictoires = $this->getNombreVictoires();

        return $this->calculerPourcentage($nombreVictoires, $nombrePartiesJouees);
    }

    private function getNombrePartiesJouees(): int
    {
        return count($this->resultats);
    }

    private function getNombreVictoires(): int
    {
        return count(array_filter(
            $this->resultats,
            fn(ResultatPartie $r) => $r->estVictoire()
        ));
    }

    private function calculerPourcentage(int $numerateur, int $denominateur): float
    {
        return round(($numerateur / $denominateur) * 100, 2);
    }
}
```

```bash
$ composer test

..                                                                  2 / 2

OK (2 tests, 2 assertions)

$ composer analyse

[OK] No errors

$ composer cs:check

[OK] 0 errors
```

## Conclusion

Le TDD garantit :
- ✅ Code testé à 100%
- ✅ Conception émergente
- ✅ Documentation vivante (les tests)
- ✅ Refactoring en confiance
- ✅ Moins de bugs en production

**Règle d'or** : Pas de code de production sans test qui l'exige.

# Architecture DDD - MonClubTT

## Vue d'ensemble

MonClubTT suit une architecture **Domain-Driven Design (DDD)** stricte avec une approche **Test-Driven Development (TDD)**.

## Structure du projet

```
MonClubTT/
├── src/
│   ├── Domain/                 # Couche Domaine (cœur métier)
│   │   ├── Model/             # Entités et Agrégats
│   │   │   ├── Joueur/
│   │   │   │   ├── Joueur.php
│   │   │   │   ├── JoueurId.php
│   │   │   │   └── JoueurRepository.php (interface)
│   │   │   ├── Equipe/
│   │   │   │   ├── Equipe.php
│   │   │   │   ├── EquipeId.php
│   │   │   │   └── EquipeRepository.php (interface)
│   │   │   └── Rencontre/
│   │   │       ├── Rencontre.php
│   │   │       ├── RencontreId.php
│   │   │       └── RencontreRepository.php (interface)
│   │   │
│   │   ├── ValueObject/       # Objets valeur (immuables)
│   │   │   ├── Licence.php
│   │   │   ├── Classement.php
│   │   │   ├── Nom.php
│   │   │   ├── Points.php
│   │   │   ├── Division.php
│   │   │   └── Score.php
│   │   │
│   │   ├── Service/           # Services du domaine
│   │   │   ├── CalculateurClassement.php
│   │   │   └── GestionnaireRencontres.php
│   │   │
│   │   ├── Event/             # Événements du domaine
│   │   │   ├── JoueurCree.php
│   │   │   ├── ClassementModifie.php
│   │   │   └── RencontreTerminee.php
│   │   │
│   │   └── Exception/         # Exceptions métier
│   │       ├── InvalidLicenceException.php
│   │       ├── InvalidClassementException.php
│   │       └── JoueurNotFoundException.php
│   │
│   ├── Application/            # Couche Application (cas d'usage)
│   │   ├── UseCase/
│   │   │   ├── Joueur/
│   │   │   │   ├── CreerJoueur/
│   │   │   │   │   ├── CreerJoueurUseCase.php
│   │   │   │   │   ├── CreerJoueurCommand.php
│   │   │   │   │   └── CreerJoueurHandler.php
│   │   │   │   ├── RecupererJoueur/
│   │   │   │   │   ├── RecupererJoueurQuery.php
│   │   │   │   │   └── RecupererJoueurHandler.php
│   │   │   │   └── MettreAJourClassement/
│   │   │   │       ├── MettreAJourClassementCommand.php
│   │   │   │       └── MettreAJourClassementHandler.php
│   │   │   │
│   │   │   └── Equipe/
│   │   │       ├── CreerEquipe/
│   │   │       └── RecupererEquipes/
│   │   │
│   │   ├── DTO/               # Data Transfer Objects
│   │   │   ├── JoueurDTO.php
│   │   │   └── EquipeDTO.php
│   │   │
│   │   └── Service/           # Services applicatifs
│   │       └── SynchronisationFFTT.php
│   │
│   └── Infrastructure/         # Couche Infrastructure
│       ├── Repository/        # Implémentations des repositories
│       │   ├── WordPressJoueurRepository.php
│       │   └── WordPressEquipeRepository.php
│       │
│       ├── API/               # Clients API externes
│       │   └── FFTTApiClient.php
│       │
│       ├── Cache/             # Système de cache
│       │   └── WordPressCache.php
│       │
│       └── Persistence/       # Mappers base de données
│           ├── JoueurMapper.php
│           └── EquipeMapper.php
│
├── tests/
│   ├── Unit/                  # Tests unitaires (isolés)
│   │   ├── Domain/
│   │   │   ├── Model/
│   │   │   │   ├── JoueurTest.php
│   │   │   │   └── EquipeTest.php
│   │   │   ├── ValueObject/
│   │   │   │   ├── LicenceTest.php
│   │   │   │   └── ClassementTest.php
│   │   │   └── Service/
│   │   │       └── CalculateurClassementTest.php
│   │   │
│   │   └── Application/
│   │       └── UseCase/
│   │           └── CreerJoueurUseCaseTest.php
│   │
│   └── Integration/           # Tests d'intégration
│       └── Infrastructure/
│           └── Repository/
│               └── WordPressJoueurRepositoryTest.php
│
├── .claude/                   # Configuration Claude Code
│   ├── coding-standard.md
│   ├── instructions.md
│   ├── hooks/
│   │   └── user-prompt-submit.sh
│   ├── prompts/
│   │   └── tdd-reminder.md
│   └── skills/
│       └── tdd-workflow.md
│
├── composer.json              # Dépendances et scripts
├── phpunit.xml                # Configuration PHPUnit
├── phpstan.neon               # Configuration PHPStan
└── README.md
```

## Bounded Contexts

### 1. Contexte Joueur

**Responsabilité** : Gestion des joueurs, licences et classements

**Entités** :
- `Joueur` (Agrégat racine)
  - Identité : `JoueurId`
  - Licence FFTT unique
  - Classement officiel et mensuel
  - Historique des parties

**Value Objects** :
- `Licence` : Numéro FFTT (7 chiffres)
- `Classement` : Points de classement (0-3500)
- `Nom` : Nom du joueur

**Règles métier** :
- Une licence est unique et valide (format FFTT)
- Un classement ne peut pas être négatif
- Le classement progresse selon les résultats

### 2. Contexte Équipe

**Responsabilité** : Gestion des équipes et compositions

**Entités** :
- `Equipe` (Agrégat racine)
  - Identité : `EquipeId`
  - Nom de l'équipe
  - Division
  - Liste des joueurs

**Value Objects** :
- `Division` : Division de l'équipe
- `NomEquipe` : Nom de l'équipe

**Règles métier** :
- Une équipe a au minimum 4 joueurs
- Les joueurs sont classés par niveau
- L'équipe appartient à une division unique

### 3. Contexte Compétition

**Responsabilité** : Gestion des rencontres et résultats

**Entités** :
- `Rencontre` (Agrégat racine)
  - Identité : `RencontreId`
  - Équipe domicile
  - Équipe extérieur
  - Score
  - Date

**Value Objects** :
- `Score` : Score de la rencontre
- `DateRencontre` : Date et heure

**Règles métier** :
- Une rencontre oppose deux équipes
- Le score est validé selon les règles FFTT
- Les résultats impactent le classement des équipes

## Principes DDD appliqués

### 1. Ubiquitous Language

Le code utilise **exactement** le vocabulaire métier de la FFTT :
- Licence (pas "ID utilisateur")
- Classement (pas "niveau" ou "rang")
- Rencontre (pas "match" ou "partie")
- Division (pas "catégorie")

### 2. Aggregates

Chaque agrégat protège ses **invariants** :

```php
final class Joueur
{
    // Invariant : Un joueur a toujours une licence valide
    public function __construct(
        private readonly JoueurId $id,
        private Licence $licence,
        private Classement $classement
    ) {
        // La validation se fait dans les Value Objects
    }

    // Invariant : Le classement ne peut que progresser ou stagner
    public function progresser(Points $points): void
    {
        $this->classement = $this->classement->ajouter($points);
    }
}
```

### 3. Value Objects

Tous les Value Objects sont **immuables** :

```php
final readonly class Licence
{
    public function __construct(private string $value)
    {
        if (!preg_match('/^\d{7}$/', $value)) {
            throw new InvalidLicenceException(
                "Format invalide : une licence doit contenir 7 chiffres"
            );
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
```

### 4. Domain Services

Pour la logique métier qui ne relève pas d'une seule entité :

```php
final readonly class CalculateurClassement
{
    public function calculerNouveauClassement(
        Joueur $joueur,
        ResultatRencontre $resultat
    ): Classement {
        // Algorithme complexe de calcul FFTT
        $pointsGagnes = $this->calculerPointsSelon(
            $joueur->getClassement(),
            $resultat->getAdversaireClassement(),
            $resultat->isVictoire()
        );

        return $joueur->getClassement()->ajouter($pointsGagnes);
    }
}
```

### 5. Repository Pattern

Les repositories masquent la persistance :

```php
// Interface dans le Domain (pas de dépendance externe)
interface JoueurRepositoryInterface
{
    public function find(JoueurId $id): ?Joueur;
    public function findByLicence(Licence $licence): ?Joueur;
    public function save(Joueur $joueur): void;
}

// Implémentation dans l'Infrastructure
final class WordPressJoueurRepository implements JoueurRepositoryInterface
{
    public function findByLicence(Licence $licence): ?Joueur
    {
        // Logique WordPress spécifique
        $data = get_option('monclubtt_joueur_' . $licence->getValue());

        if (!$data) {
            return null;
        }

        return $this->mapper->toDomain($data);
    }
}
```

### 6. Domain Events

Pour la communication entre agrégats :

```php
final readonly class ClassementModifie implements DomainEvent
{
    public function __construct(
        private JoueurId $joueurId,
        private Classement $ancienClassement,
        private Classement $nouveauClassement,
        private DateTimeImmutable $occurredOn
    ) {}

    // Getters...
}
```

## Flux de données

### Command (écriture)

```
Controller/Shortcode
    ↓
CreerJoueurCommand
    ↓
CreerJoueurHandler (Use Case)
    ↓
Joueur::__construct() (Domain)
    ↓
JoueurRepository->save() (Infrastructure)
    ↓
WordPress Options API
```

### Query (lecture)

```
Controller/Shortcode
    ↓
RecupererJoueurQuery
    ↓
RecupererJoueurHandler (Use Case)
    ↓
JoueurRepository->find() (Infrastructure)
    ↓
JoueurDTO (transformation)
    ↓
View
```

## Tests

### Tests unitaires (Domain)

Testent la **logique métier** en isolation :

```php
final class JoueurTest extends TestCase
{
    public function test_should_create_joueur_with_valid_data(): void
    {
        // Arrange
        $id = JoueurId::generate();
        $licence = new Licence('1234567');
        $classement = new Classement(1000);

        // Act
        $joueur = new Joueur($id, $licence, $classement);

        // Assert
        $this->assertTrue($joueur->getLicence()->equals($licence));
        $this->assertEquals(1000, $joueur->getClassement()->getPoints());
    }
}
```

### Tests d'intégration (Infrastructure)

Testent l'**infrastructure réelle** :

```php
final class WordPressJoueurRepositoryTest extends WP_UnitTestCase
{
    public function test_should_save_and_retrieve_joueur(): void
    {
        // Arrange
        $repository = new WordPressJoueurRepository(new JoueurMapper());
        $joueur = new Joueur(
            JoueurId::generate(),
            new Licence('1234567'),
            new Classement(1000)
        );

        // Act
        $repository->save($joueur);
        $retrieved = $repository->findByLicence(new Licence('1234567'));

        // Assert
        $this->assertNotNull($retrieved);
        $this->assertTrue($joueur->getId()->equals($retrieved->getId()));
    }
}
```

## Migration progressive

Pour migrer le code existant vers DDD :

1. **Créer les Value Objects** pour les concepts métier
2. **Extraire les entités** du code existant
3. **Écrire les tests** pour le comportement actuel
4. **Refactorer** vers la nouvelle architecture
5. **Vérifier** que les tests passent toujours

**Priorité** :
1. Contexte Joueur (plus simple)
2. Contexte Équipe
3. Contexte Compétition (plus complexe)

## Commandes utiles

```bash
# Installation
composer install

# Tests
composer test                # Tous les tests
composer test:coverage       # Avec couverture
composer test -- --filter=Joueur  # Tests spécifiques

# Qualité
composer analyse             # PHPStan niveau 9
composer cs:check            # Standards PSR-12
composer cs:fix              # Correction auto

# Qualité complète
composer quality             # Tests + Analyse + Standards
```

## Ressources

- [DDD by Eric Evans](https://www.domainlanguage.com/ddd/)
- [PHP 8.2 Documentation](https://www.php.net/releases/8.2/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [PHPUnit Documentation](https://phpunit.de/)

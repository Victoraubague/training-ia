<?php
ini_set('memory_limit', '-1'); // Pas de limite mémoire
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require 'vendor/autoload.php';

use Phpml\Classification\DecisionTree;

// Fonction pour charger les images depuis le dossier de test
function loadImagesForTesting(string $directory, int $size = 28): array
{
    echo "Chargement des images de test depuis le répertoire '$directory'...\n";
    $samples = [];
    $labels = [];
    $totalImages = 0;

    for ($label = 0; $label <= 9; $label++) {
        $folder = rtrim($directory, '/') . '/' . $label;

        if (!is_dir($folder)) {
            echo "Dossier $label introuvable, passage au suivant...\n";
            continue;
        }

        echo "Traitement des images dans le dossier : $folder\n";
        $images = glob($folder . '/*.{png,jpg,jpeg,bmp}', GLOB_BRACE);

        if (empty($images)) {
            echo "Aucune image trouvée dans $folder.\n";
            continue;
        }

        foreach ($images as $imagePath) {
            $img = @imagecreatefromstring(file_get_contents($imagePath));
            if (!$img) {
                echo "Image invalide ou corrompue : $imagePath\n";
                continue;
            }

            imagefilter($img, IMG_FILTER_GRAYSCALE);
            $resized = imagecreatetruecolor($size, $size);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $size, $size, imagesx($img), imagesy($img));

            $pixels = [];
            for ($y = 0; $y < $size; $y++) {
                for ($x = 0; $x < $size; $x++) {
                    $rgb = imagecolorat($resized, $x, $y);
                    $gray = $rgb & 0xFF;
                    $pixels[] = $gray / 255.0; // Normaliser entre 0 et 1
                }
            }

            imagedestroy($img);
            imagedestroy($resized);

            $samples[] = $pixels;
            $labels[] = (string)$label;
            $totalImages++;
        }
    }

    echo "Chargement terminé. Total des images de test chargées : $totalImages\n";
    return [$samples, $labels];
}

// Charger les données de test
echo "Étape 1 : Chargement des données de test...\n";
list($X_test, $y_test) = loadImagesForTesting('testing');

// Vérifier les données
if (empty($X_test) || empty($y_test)) {
    die("Erreur : Aucune donnée de test valide n'a été chargée.\n");
}
echo "Nombre total d'échantillons de test : " . count($X_test) . "\n";
print_r(array_count_values($y_test)); // Vérifier la distribution des classes

// Charger le modèle sauvegardé
echo "Étape 2 : Chargement du modèle DecisionTree...\n";
if (!file_exists('decision_tree_model.json')) {
    die("Erreur : Le modèle 'decision_tree_model.json' est introuvable. Entraînez le modèle d'abord.\n");
}
$tree = unserialize(file_get_contents('decision_tree_model.json'));
echo "Modèle chargé avec succès.\n";

// Prédire les labels pour les données de test
echo "Étape 3 : Prédiction des labels...\n";
$predictions = $tree->predict($X_test);
echo "Prédiction terminée.\n";

// Calculer la précision
echo "Étape 4 : Calcul de la précision...\n";
$totalSamples = count($y_test);
$correct = 0;

foreach ($y_test as $index => $trueLabel) {
    if ($trueLabel === $predictions[$index]) {
        $correct++;
    }
}

$accuracy = ($correct / $totalSamples) * 100;
echo "Précision globale : " . round($accuracy, 2) . "% ($correct / $totalSamples).\n";

// Afficher les erreurs de classification
function showErrors(array $y_test, array $predictions)
{
    echo "\nÉtape 5 : Analyse des erreurs...\n";
    $errorCount = 0;

    foreach ($y_test as $index => $expected) {
        if ($expected !== $predictions[$index]) {
            echo "Erreur : Attendu = $expected, Prédit = " . $predictions[$index] . "\n";
            $errorCount++;
        }
    }

    echo "Total des erreurs : $errorCount / " . count($y_test) . "\n";
}

showErrors($y_test, $predictions);

?>

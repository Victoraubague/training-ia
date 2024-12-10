<?php
ini_set('memory_limit', '-1'); // Pas de limite mémoire
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require 'vendor/autoload.php';

use Phpml\Classification\DecisionTree;

// Fonction pour charger exactement 1000 images par catégorie
function loadImagesPerCategory(string $directory, int $size = 28, int $imagesPerCategory = 1000): array
{
    echo "Chargement de $imagesPerCategory images par catégorie depuis le répertoire '$directory'...\n";
    $samples = [];
    $labels = [];
    $categoriesLoaded = 0;

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

        $images = array_slice($images, 0, $imagesPerCategory); // Prendre seulement les N premières images
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
        }

        echo "Catégorie $label : " . count($images) . " images chargées.\n";
        $categoriesLoaded++;
    }

    echo "Chargement terminé. Total des catégories chargées : $categoriesLoaded\n";
    return [$samples, $labels];
}

// Charger les données d'entraînement
echo "Étape 1 : Chargement des données d'entraînement...\n";
list($X_train, $y_train) = loadImagesPerCategory('training', $size = 28, $imagesPerCategory = 1000);

// Vérifier les données
if (empty($X_train) || empty($y_train)) {
    die("Erreur : Aucune donnée d'entraînement valide n'a été chargée.\n");
}
echo "Nombre total d'échantillons d'entraînement : " . count($X_train) . "\n";
print_r(array_count_values($y_train));

// j'init et fais le test pr l'arbre
echo "Étape 2 : Initialisation et entraînement du modèle DecisionTree...\n";
$tree = new DecisionTree($maxDepth = 15, $minSamplesSplit = 10); // settings la profondeur à 10
echo "Début de l'entraînement...\n";
$tree->train($X_train, $y_train);
echo "Entraînement terminé pour DecisionTree.\n";

// Save le modèle pr le test après
echo "Étape 3 : Sauvegarde du modèle...\n";
file_put_contents('decision_tree_model.json', serialize($tree));
echo "Modèle sauvegardé dans 'decision_tree_model.json'.\n";
?>

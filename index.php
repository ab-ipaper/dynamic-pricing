<?php
// Disable Error Reporting
error_reporting(1);
ini_set("display_errors", 1);

// Configuration settings
define("XML_FEED_PATH", "product_feed.xml");
define("MAX_WIDTH", 2000);
define("MAX_HEIGHT", 1000);
define("FALLBACK_WIDTH", 1000);
define("FALLBACK_HEIGHT", 1415);
define("CORNER_RADIUS", 1);
define("FONT_PATH", "fonts/Kanit.ttf");
define("CACHE_DIRECTORY", "cache/");
define("CACHE_EXPIRATION_TIME", 7 * 24 * 60 * 60); // 7 days in seconds

// Error messages
define("ERROR_INVALID_ID", "Invalid product ID.");
define("ERROR_NO_ID", "No product ID provided in the string query");
define("ERROR_PRODUCT_NOT_FOUND", "Product not found.");
define("ERROR_INVALID_WIDTH", "Invalid width value.");
define("ERROR_INVALID_HEIGHT", "Invalid height value.");

// Load the product data from the feed
function loadProductData($id)
{
    $feedContent = file_get_contents(XML_FEED_PATH);
    $xml = simplexml_load_string(
        $feedContent,
        null,
        0,
        "http://www.w3.org/2005/Atom"
    );

    foreach ($xml->entry as $entry) {
        $entryId = (string) $entry->children("g", true)->id;
        if ($entryId === $id) {
            return $entry;
        }
    }

    return null;
}

// Validate and sanitize the ID parameter
function validateProductId($id)
{
    // Whitelist of allowed characters: alphanumeric, hyphen, and underscore
    $sanitizedId = preg_replace("/[^a-zA-Z0-9\-_]/", "", $id);

    if (empty($sanitizedId) || $sanitizedId !== $id) {
        throw new Exception(ERROR_INVALID_ID);
    }

    return $sanitizedId;
}

// Validate and sanitize the width parameter
function validateWidth($width)
{
    // Sanitize the width by ensuring it is a positive integer within the allowed range
    $sanitizedWidth = filter_var($width, FILTER_VALIDATE_INT, [
        "options" => [
            "min_range" => 1,
            "max_range" => MAX_WIDTH,
        ],
    ]);

    if ($sanitizedWidth === false) {
        throw new Exception(ERROR_INVALID_WIDTH);
    }

    return $sanitizedWidth;
}

// Validate and sanitize the height parameter
function validateHeight($height)
{
    // Sanitize the height by ensuring it is a positive integer within the allowed range
    $sanitizedHeight = filter_var($height, FILTER_VALIDATE_INT, [
        "options" => [
            "min_range" => 1,
            "max_range" => MAX_HEIGHT,
        ],
    ]);

    if ($sanitizedHeight === false) {
        throw new Exception(ERROR_INVALID_HEIGHT);
    }

    return $sanitizedHeight;
}

// Generate the product image
function generateProductImage($product, $width, $height)
{
    // Get the product ID and price
    $productId = (string) $product->children("g", true)->id;
    $price = (string) $product->children("g", true)->price;

    // Create a unique cache key for the image including the price
    $cacheKey = md5($productId . "-" . $price);

    // Check if the image already exists in the cache directory
    $cachePath = CACHE_DIRECTORY . $cacheKey . ".png";
    if (
        file_exists($cachePath) &&
        filemtime($cachePath) > time() - CACHE_EXPIRATION_TIME
    ) {
        // Serve the cached image
        header("Content-Type: image/png");
        readfile($cachePath);
        exit();
    }

    // Create a new image with the specified dimensions and transparent background
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    $transparentColor = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparentColor);

    // Access the product price
    $price = (string) $product->children("g", true)->price;

    // Create a new color for the price label (black)
    $priceColorHex = "#f0f0e9"; // Hexadecimal color value for black
    $priceColorRgb = sscanf($priceColorHex, "#%2x%2x%2x");
    $priceColor = imagecolorallocate(
        $image,
        $priceColorRgb[0],
        $priceColorRgb[1],
        $priceColorRgb[2]
    );

    // Set the font path
    $fontPath = FONT_PATH;

    // Calculate the font size based on the canvas width and the number of characters in the price
    $priceFontSizePercentage = 1; // Adjust the percentage as needed
    $priceFontSize = ($width * $priceFontSizePercentage) / strlen($price);

    // Calculate the x and y coordinates for centering the price label
    $priceBoundingBox = imagettfbbox(
        $priceFontSize,
        0,
        $fontPath,
        $price
    );
  
    $priceX = ($width - $priceBoundingBox[2] - $priceBoundingBox[0]) / 2;
    $priceY = ($height - $priceBoundingBox[1] - $priceBoundingBox[7]) / 2;

    // Add the price label to the image
    imagettftext(
        $image,
        $priceFontSize,
        0,
        $priceX,
        $priceY,
        $priceColor,
        $fontPath,
        $price
    );

    // Save the image to the cache directory
    imagepng($image, $cachePath);

    // Set the output format to PNG
    header("Content-Type: image/png");

    // Output the image content to the browser
    imagepng($image);

    // Destroy the image
    imagedestroy($image);
}

// Main code execution
$id = isset($_GET["id"]) ? trim($_GET["id"]) : null;
if (empty($id)) {
    echo ERROR_NO_ID;
    exit();
}

try {
    $productId = validateProductId($id);

    $product = loadProductData($productId);
    if (!$product) {
        echo ERROR_PRODUCT_NOT_FOUND;
        exit();
    }

    $width = isset($_GET["w"]) ? intval($_GET["w"]) : FALLBACK_WIDTH;
    $width = validateWidth($width);

    $height = isset($_GET["h"]) ? intval($_GET["h"]) : FALLBACK_HEIGHT;
    $height = validateHeight($height);

    generateProductImage($product, $width, $height);
} catch (Exception $e) {
    echo $e->getMessage();
    exit();
}
?>

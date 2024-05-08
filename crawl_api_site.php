<?php
header("Content-Type: application/json");

include "db.php";
include "simple_html_dom.php";
/*
Edit this if you know what they are for.
*/
$maxRequests = 20;
$rateIncrease = 1;
$rateDecreaseInterval = 5; // in seconds

/*
DO NOT CHANGE THE BELOW IF YOU DO NOT KNOW
HOW TO SET IT UP
*/
$url = isset($_GET["url"]) ? rtrim($_GET["url"], '"') : null;

$timestamp = time();
$currentRate = apcu_fetch("rate_limit", $success);
if (!$success) {
    apcu_add("rate_limit", 0, $rateDecreaseInterval);
    $currentRate = 0;
}

if ($currentRate >= $maxRequests) {
    http_response_code(429);
    echo json_encode(["error" => "Rate limit exceeded"]);
    exit();
}

if ($url && strpos($url, "https://twemoji.maxcdn.com/") === false) {
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $stmt = $pdo->prepare("SELECT * FROM website_meta WHERE url = ?");
        $stmt->execute([$url]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $updatedAt = strtotime($row["updated_at"]);
            $currentTime = time();
            $twoHoursAgo = $currentTime - 2 * 60 * 60;

            if ($updatedAt < $twoHoursAgo) {
                $html = file_get_contents($url);
                if ($html === false) {
                    throw new Exception("Failed to crawl");
                }

                $dom = new simple_html_dom();
                $dom->load($html);

                // $title = $dom->find('title', 0) ? $dom->find('title', 0)->plaintext : "";
                $titleMetaTag = $dom->find("meta[property=og:title]", 0);

                if (!$titleMetaTag) {
                    $titleMetaTag = $dom->find("meta[name=title]", 0);
                }

                $title = $titleMetaTag ? $titleMetaTag->content : "";

                $descriptionMetaTag = $dom->find("meta[name=description]", 0);
                $description = $descriptionMetaTag
                    ? $descriptionMetaTag->content
                    : "";

                if (empty($description)) {
                    $ogDescriptionMetaTag = $dom->find(
                        "meta[property=og:description]",
                        0
                    );
                    $description = $ogDescriptionMetaTag
                        ? $ogDescriptionMetaTag->content
                        : "";
                }

                if (empty($description)) {
                    $ogDescriptionMetaTagByName = $dom->find(
                        "meta[name=og:description]",
                        0
                    );
                    $description = $ogDescriptionMetaTagByName
                        ? $ogDescriptionMetaTagByName->content
                        : "";
                }

                $image = $dom->find("meta[property=og:image]", 0)
                    ? $dom->find("meta[property=og:image]", 0)->content
                    : "";
                $ogSiteNameMetaTag = $dom->find(
                    "meta[property=og:site_name]",
                    0
                );
                $siteName = $ogSiteNameMetaTag
                    ? $ogSiteNameMetaTag->content
                    : "";

                if (empty($siteName)) {
                    $ogSiteNameMetaTagByName = $dom->find(
                        "meta[name=og:site_name]",
                        0
                    );
                    $siteName = $ogSiteNameMetaTagByName
                        ? $ogSiteNameMetaTagByName->content
                        : "";
                }

                $stmt = $pdo->prepare(
                    "UPDATE website_meta SET title = ?, description = ?, image = ?, site_name = ?, updated_at = NOW() WHERE url = ?"
                );
                $stmt->execute([$title, $description, $image, $siteName, $url]);
                echo json_encode([
                    "url" => $url,
                    "title" => $title,
                    "description" => $description,
                    "image" => $image,
                    "site_name" => $siteName,
                ]);

                $dom->clear();
            } else {
                echo json_encode($row);
            }
            //echo json_encode($row);
        } else {
            $html = file_get_contents($url);

            $dom = new simple_html_dom();
            $dom->load($html);

            // $title = $dom->find('title', 0) ? $dom->find('title', 0)->plaintext : "";
            $titleMetaTag = $dom->find("meta[property=og:title]", 0);

            if (!$titleMetaTag) {
                $titleMetaTag = $dom->find("meta[name=title]", 0);
            }

            $title = $titleMetaTag ? $titleMetaTag->content : "";

            $descriptionMetaTag = $dom->find("meta[name=description]", 0);
            $description = $descriptionMetaTag
                ? $descriptionMetaTag->content
                : "";

            if (empty($description)) {
                $ogDescriptionMetaTag = $dom->find(
                    "meta[property=og:description]",
                    0
                );
                $description = $ogDescriptionMetaTag
                    ? $ogDescriptionMetaTag->content
                    : "";
            }

            if (empty($description)) {
                $ogDescriptionMetaTagByName = $dom->find(
                    "meta[name=og:description]",
                    0
                );
                $description = $ogDescriptionMetaTagByName
                    ? $ogDescriptionMetaTagByName->content
                    : "";
            }

            $image = $dom->find("meta[property=og:image]", 0)
                ? $dom->find("meta[property=og:image]", 0)->content
                : "";
            $ogSiteNameMetaTag = $dom->find("meta[property=og:site_name]", 0);
            $siteName = $ogSiteNameMetaTag ? $ogSiteNameMetaTag->content : "";

            if (empty($siteName)) {
                $ogSiteNameMetaTagByName = $dom->find(
                    "meta[name=og:site_name]",
                    0
                );
                $siteName = $ogSiteNameMetaTagByName
                    ? $ogSiteNameMetaTagByName->content
                    : "";
            }

            /*
            $ogSiteNameTag = $dom->find('meta[property=og:site_name]', 0);
            if ($ogSiteNameTag) {
                $siteName = $ogSiteNameTag->content;
            }

            $ogImageTag = $dom->find('meta[property=og:image]', 0);
            if ($ogImageTag) {
                $image = $ogImageTag->content;
            }
          */

            $stmt = $pdo->prepare("INSERT INTO website_meta (url, title, description, image, site_name)
                                   VALUES (?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE
                                   title = VALUES(title),
                                   description = VALUES(description),
                                   image = VALUES(image),
                                   site_name = VALUES(site_name)");
            $stmt->execute([$url, $title, $description, $image, $siteName]);

            echo json_encode([
                "url" => $url,
                "title" => $title,
                "description" => $description,
                "image" => $image,
                "site_name" => $siteName,
            ]);

            $dom->clear();
        }

        apcu_store("rate_limit", ++$currentRate, $rateDecreaseInterval);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid URL format."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "URL parameter is missing or invalid."]);
}
?>

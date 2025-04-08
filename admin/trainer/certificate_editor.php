<?php
// certificate_editor.php (Production-Ready)

// Disable debug output in production
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// Include admin header (which sets security headers including CSP and generates $cspNonce)
require_once __DIR__ . '/../admin_header.php';
require_once '../../backend/db/db_connect.php';

// Allow both trainers and admins to access
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'trainer' && $_SESSION['role'] !== 'admin')) {
    header('Location: /capstone-php/index.php');
    exit();
}

// Validate the training_id
if (!isset($_GET['training_id'])) {
    echo "Training ID not provided.";
    exit();
}
$training_id = intval($_GET['training_id']);
if ($training_id < 1) {
    echo "Invalid Training ID.";
    exit();
}

// Optional: fetch the training title from DB. For demo, use placeholder:
$training_title = "Training Title";

// Fetch participants for this training
$participants = [];
$stmt = $conn->prepare("
    SELECT u.user_id, u.first_name, u.last_name
    FROM training_registrations tr
    JOIN users u ON tr.user_id = u.user_id
    WHERE tr.training_id = ?
    ORDER BY u.first_name, u.last_name
");
$stmt->bind_param("i", $training_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $participants[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Editor - ADOHRE</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style nonce="<?= $cspNonce ?>">
        /* Container for the Fabric canvas (A4 landscape: 1123px x 792px) */
        #canvas-container {
            width: 100%;
            max-width: 1123px;
            aspect-ratio: 1123 / 792;
            margin: 20px auto;
            position: relative;
            border: 1px solid #ccc;
        }

        #certificateCanvas {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Design Selector */
        #designSelector {
            display: flex;
            overflow-x: auto;
            gap: 10px;
            margin-bottom: 20px;
        }

        #designSelector img {
            width: 150px;
            cursor: pointer;
            border: 2px solid transparent;
        }

        #designSelector img.selected {
            border-color: #28a745;
        }
    </style>
    <!-- Fabric.js -->
    <script src="https://cdn.jsdelivr.net/npm/fabric@4.6.0/dist/fabric.min.js" nonce="<?= $cspNonce ?>"></script>
</head>

<body>
    <div class="container mt-4">
        <h1>Certificate Editor</h1>
        <p>
            Designing certificate for Training ID:
            <strong><?= htmlspecialchars($training_id); ?></strong>
            ( <strong><?= htmlspecialchars($training_title); ?></strong> )<br>
            Date: <strong><?= date("Y-m-d"); ?></strong>
        </p>
        <!-- Design Selector (pre-made backgrounds) -->
        <div id="designSelector">
            <img src="../../assets/design1.png" alt="Design 1">
            <img src="../../assets/design2.png" alt="Design 2">
            <img src="../../assets/design3.png" alt="Design 3">
        </div>
        <form id="certificateForm">
            <input type="hidden" id="selectedDesign" name="selected_design" value="">
            <input type="hidden" name="training_id" value="<?= htmlspecialchars($training_id); ?>">
            <!-- Background image upload -->
            <div class="mb-3">
                <label for="bgImage" class="form-label">Upload Certificate Template (A4 Landscape recommended)</label>
                <input type="file" id="bgImage" name="certificate_background" accept="image/*" class="form-control">
            </div>
            <!-- Canvas Container -->
            <div id="canvas-container">
                <canvas id="certificateCanvas"></canvas>
            </div>
            <!-- Editor Controls -->
            <div class="row mt-3">
                <div class="col-md-2 mb-3">
                    <button type="button" id="addTextBtn" class="btn btn-secondary w-100">Add Text</button>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="button" id="clearCanvasBtn" class="btn btn-warning w-100">Clear Canvas</button>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="fontFamilySelect" class="form-label">Font Style</label>
                    <select id="fontFamilySelect" class="form-select">
                        <option value="Arial" selected>Arial</option>
                        <option value="Times New Roman">Times New Roman</option>
                        <option value="Courier New">Courier New</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Verdana">Verdana</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="button" id="prevCertBtn" class="btn btn-info w-100">Previous</button>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="button" id="nextCertBtn" class="btn btn-info w-100">Next</button>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="button" id="saveThisLayoutBtn" class="btn btn-success w-100">Save This Layout</button>
                </div>
            </div>
            <!-- Additional Controls -->
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="lineColor" class="form-label">Line Color</label>
                    <input type="color" id="lineColor" class="form-control" value="#000000">
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" id="addLineBtn" class="btn btn-primary w-100 mt-4">Add Line</button>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="textColorPicker" class="form-label">Text Color</label>
                    <input type="color" id="textColorPicker" class="form-control" value="#000000">
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" id="changeTextColorBtn" class="btn btn-secondary w-100 mt-4">Change Text
                        Color</button>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="lineThickness" class="form-label">Line Thickness</label>
                    <input type="number" id="lineThickness" class="form-control" value="2" min="1" max="20">
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" id="changeLineThicknessBtn" class="btn btn-primary w-100 mt-4">Set Line
                        Thickness</button>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" id="deleteObjectBtn" class="btn btn-danger w-100 mt-4">Delete Object</button>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="addImageInput" class="form-label">Add Another Image</label>
                    <input type="file" id="addImageInput" name="add_image" accept="image/*" class="form-control">
                </div>
            </div>
            <!-- Preview Section -->
            <div class="row mt-3">
                <div class="col-md-9">
                    <label for="previewUserId" class="form-label">Preview As (Participant)</label>
                    <select id="previewUserId" name="preview_user_id" class="form-select">
                        <?php foreach ($participants as $p): ?>
                            <option value="<?= $p['user_id']; ?>">
                                <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="saveAllLayoutsBtn" class="btn btn-success w-100 mt-4">Save All
                        Layouts</button>
                </div>
            </div>
            <a href="assessments.php" class="btn btn-info mt-3">Back to Assessments</a>
        </form>
    </div>

    <!-- Main Editor Script (inline, with nonce) -->
    <script nonce="<?= $cspNonce ?>">
        // Production-ready inline script

        const TRAINING_ID = <?= json_encode($training_id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const DEFAULT_WIDTH = 1123;
        const DEFAULT_HEIGHT = 792;
        const canvas = new fabric.Canvas('certificateCanvas', {
            width: DEFAULT_WIDTH,
            height: DEFAULT_HEIGHT
        });
        const container = document.getElementById('canvas-container');
        container.style.width = DEFAULT_WIDTH + 'px';
        container.style.height = DEFAULT_HEIGHT + 'px';

        // Basic placeholders
        const trainingTitlePlaceholder = new fabric.IText('[Training Title]', {
            left: canvas.width / 2,
            top: 50,
            fontFamily: 'Arial',
            fill: '#000',
            fontSize: 28,
            originX: 'center',
            selectable: true
        });
        canvas.add(trainingTitlePlaceholder);

        const datePlaceholder = new fabric.IText('[Date]', {
            left: 50,
            top: canvas.height - 70,
            fontFamily: 'Arial',
            fill: '#000',
            fontSize: 24,
            selectable: true
        });
        datePlaceholder.text = new Date().toISOString().split('T')[0];
        canvas.add(datePlaceholder);

        const namePlaceholder = new fabric.IText('[Name]', {
            left: canvas.width / 2,
            top: 220,
            fontFamily: 'Arial',
            fill: '#000',
            fontSize: 36,
            originX: 'center',
            textAlign: 'center',
            selectable: true,
            placeholderType: 'name'
        });
        canvas.add(namePlaceholder);

        const bodyPlaceholder = new fabric.IText('Lorem ipsum dolor sit amet, consectetur adipiscing elit.', {
            left: canvas.width / 2,
            top: 300,
            fontFamily: 'Arial',
            fill: '#000',
            fontSize: 18,
            originX: 'center',
            textAlign: 'center',
            selectable: true
        });
        canvas.add(bodyPlaceholder);

        function updateNamePlaceholder() {
            const select = document.getElementById('previewUserId');
            if (!select || select.selectedIndex < 0) {
                // In production, avoid logging detailed errors.
                // console.error("No participant selected in dropdown");
                return;
            }

            // Get the selected participant's name.
            const selectedText = select.options[select.selectedIndex].text;
            // Optionally sanitize selectedText here if needed.

            // Uncomment the following line if you need minimal debug output in development:
            // console.log("Updating name placeholder to:", selectedText);

            let namePlaceholders = [];

            // Method 1: Find by placeholderType property.
            canvas.getObjects('i-text').forEach(function(obj) {
                if (obj.placeholderType === 'name') {
                    // console.log("Found placeholder by placeholderType:", obj);
                    namePlaceholders.push(obj);
                }
            });

            // Method 2: Find by text content.
            if (namePlaceholders.length === 0) {
                canvas.getObjects('i-text').forEach(function(obj) {
                    if (obj.text && (
                            obj.text === '[Name]' ||
                            obj.text.includes('[Name]') ||
                            (obj.originalText && obj.originalText.includes('[Name]'))
                        )) {
                        // console.log("Found placeholder by text content:", obj);
                        obj.placeholderType = 'name';
                        obj.originalText = obj.originalText || obj.text;
                        namePlaceholders.push(obj);
                    }
                });
            }

            // Method 3: Find by a name-like regex pattern.
            if (namePlaceholders.length === 0) {
                canvas.getObjects('i-text').forEach(function(obj) {
                    if (obj.text && /^[A-Z][a-z]+ [A-Z][a-z]+/.test(obj.text)) {
                        // console.log("Found placeholder by name pattern:", obj);
                        obj.placeholderType = 'name';
                        obj.originalText = obj.originalText || obj.text;
                        namePlaceholders.push(obj);
                    }
                });
            }

            // Method 4: Use prominence (position and font size).
            if (namePlaceholders.length === 0) {
                let prominentObjects = [];
                canvas.getObjects('i-text').forEach(function(obj) {
                    let score = 0;
                    if (obj.originX === 'center') score += 5;
                    if (obj.top > canvas.height * 0.2 && obj.top < canvas.height * 0.7) score += 3;
                    score += Math.min(10, obj.fontSize / 5);
                    prominentObjects.push({
                        obj,
                        score
                    });
                });
                prominentObjects.sort((a, b) => b.score - a.score);
                if (prominentObjects.length > 0) {
                    const topObj = prominentObjects[0].obj;
                    // console.log("Found placeholder by prominence (score=" + prominentObjects[0].score + "):", topObj);
                    topObj.placeholderType = 'name';
                    topObj.originalText = topObj.originalText || topObj.text;
                    namePlaceholders.push(topObj);
                }
            }

            // Update all found placeholders with the selected participant's name.
            if (namePlaceholders.length > 0) {
                namePlaceholders.forEach(function(obj) {
                    if (!obj.originalText) {
                        obj.originalText = obj.text;
                    }
                    // console.log("Setting placeholder text from:", obj.text, "to:", selectedText);
                    obj.set('text', selectedText);
                });
                canvas.renderAll();
                // console.log("Updated", namePlaceholders.length, "name placeholder(s)");
            } else {
                // Optionally, handle the case when no placeholder is found.
                // console.warn("No name placeholder found in the canvas!");
            }
        }


        // Participant navigation event listeners
        document.getElementById('previewUserId').addEventListener('change', updateNamePlaceholder);
        document.getElementById('prevCertBtn').addEventListener('click', () => {
            const select = document.getElementById('previewUserId');
            const idx = select.selectedIndex;
            select.selectedIndex = idx > 0 ? idx - 1 : select.options.length - 1;
            updateNamePlaceholder();
        });
        document.getElementById('nextCertBtn').addEventListener('click', () => {
            const select = document.getElementById('previewUserId');
            const idx = select.selectedIndex;
            select.selectedIndex = idx < select.options.length - 1 ? idx + 1 : 0;
            updateNamePlaceholder();
        });

        // Background upload
        document.getElementById('bgImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(evt) {
                fabric.Image.fromURL(evt.target.result, function(img) {
                    img.scaleX = DEFAULT_WIDTH / img.width;
                    img.scaleY = DEFAULT_HEIGHT / img.height;
                    canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), {
                        originX: 'left',
                        originY: 'top'
                    });
                });
            };
            reader.readAsDataURL(file);
        });

        // Design thumbnails
        document.querySelectorAll('#designSelector img').forEach(thumb => {
            thumb.addEventListener('click', function() {
                document.querySelectorAll('#designSelector img').forEach(img => img.classList.remove(
                    'selected'));
                this.classList.add('selected');
                const bgUrl = this.src;
                document.getElementById('selectedDesign').value = bgUrl;
                fabric.Image.fromURL(bgUrl, function(img) {
                    img.scaleX = DEFAULT_WIDTH / img.width;
                    img.scaleY = DEFAULT_HEIGHT / img.height;
                    canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), {
                        originX: 'left',
                        originY: 'top'
                    });
                });
            });
        });

        // Add Text
        document.getElementById('addTextBtn').addEventListener('click', () => {
            const textObj = new fabric.IText('Your Text Here', {
                left: 100,
                top: 100,
                fontFamily: 'Arial',
                fill: '#000',
                fontSize: 36,
                selectable: true
            });
            canvas.add(textObj);
        });

        // Clear Canvas
        document.getElementById('clearCanvasBtn').addEventListener('click', () => {
            const bg = canvas.backgroundImage;
            canvas.clear();
            if (bg) {
                canvas.setBackgroundImage(bg, canvas.renderAll.bind(canvas));
            }
            canvas.add(trainingTitlePlaceholder, datePlaceholder, namePlaceholder, bodyPlaceholder);
            canvas.renderAll();
        });

        // Change Font Family
        document.getElementById('fontFamilySelect').addEventListener('change', function() {
            const newFont = this.value;
            const activeObj = canvas.getActiveObject();
            if (activeObj && (activeObj.type === 'i-text' || activeObj.type === 'textbox')) {
                activeObj.set({
                    fontFamily: newFont
                });
                canvas.renderAll();
            }
        });

        // Add Line
        document.getElementById('addLineBtn').addEventListener('click', () => {
            const lineColor = document.getElementById('lineColor').value;
            const line = new fabric.Line([50, 50, 300, 50], {
                stroke: lineColor,
                strokeWidth: 2,
                selectable: true
            });
            canvas.add(line);
        });

        // Change Text Color
        document.getElementById('changeTextColorBtn').addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj && (activeObj.type === 'i-text' || activeObj.type === 'textbox')) {
                activeObj.set({
                    fill: document.getElementById('textColorPicker').value
                });
                canvas.renderAll();
            } else {
                alert("Please select a text object first.");
            }
        });

        // Change Line Thickness
        document.getElementById('changeLineThicknessBtn').addEventListener('click', () => {
            const thickness = parseFloat(document.getElementById('lineThickness').value);
            const activeObj = canvas.getActiveObject();
            if (activeObj && activeObj.type === 'line') {
                activeObj.set({
                    strokeWidth: thickness
                });
                canvas.renderAll();
            } else {
                alert("Please select a line object first.");
            }
        });

        // Delete Object
        document.getElementById('deleteObjectBtn').addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                canvas.remove(activeObj);
                canvas.renderAll();
            } else {
                alert("Please select an object to delete.");
            }
        });

        // Add Another Image
        document.getElementById('addImageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(evt) {
                fabric.Image.fromURL(evt.target.result, function(img) {
                    img.set({
                        left: canvas.width / 2 - img.width / 2,
                        top: canvas.height / 2 - img.height / 2
                    });
                    canvas.add(img);
                    canvas.renderAll();
                });
            };
            reader.readAsDataURL(file);
        });

        // Save This Layout (for current participant)
        document.getElementById('saveThisLayoutBtn').addEventListener('click', () => {
            const previewUserId = document.getElementById('previewUserId').value;
            if (!previewUserId) {
                alert("No participant selected.");
                return;
            }
            const layoutImage = canvas.toDataURL('image/png', 1.0);
            const canvasJSON = JSON.stringify(canvas.toJSON());

            const formData = new FormData(document.getElementById('certificateForm'));
            formData.delete('add_image');
            formData.append('final_image', layoutImage);
            formData.append('canvas_json', canvasJSON);
            formData.append('preview_user_id', previewUserId);
            formData.append('action', 'save_certificate_layout_single');

            fetch('../../backend/models/generate_certificate.php', {
                    method: 'POST',
                    body: formData
                })
                .then(resp => resp.json())
                .then(data => {
                    if (data.status) {
                        alert('Layout saved for the selected participant.');
                    } else {
                        alert('Error: ' + (data.message || 'Unknown'));
                    }
                })
                .catch(() => alert('Failed to save layout.'));
        });

        // Save All Layouts (for every participant)
        document.getElementById('saveAllLayoutsBtn').addEventListener('click', () => {
            const btn = document.getElementById('saveAllLayoutsBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Saving...';
            btn.disabled = true;

            const layoutImage = canvas.toDataURL('image/png', 1.0);
            const canvasJSON = JSON.stringify(canvas.toJSON());
            const formData = new FormData(document.getElementById('certificateForm'));
            formData.delete('add_image');
            formData.append('final_image', layoutImage);
            formData.append('canvas_json', canvasJSON);
            formData.append('action', 'save_certificate_layout_all');

            fetch('../../backend/models/generate_certificate.php', {
                    method: 'POST',
                    body: formData
                })
                .then(resp => resp.json())
                .then(data => {
                    if (data.status) {
                        alert('Layouts saved for all participants.');
                    } else {
                        alert('Error: ' + (data.message || 'Unknown'));
                    }
                })
                .catch(() => alert('Failed to save layouts.'))
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        });

        // Load saved layout JSON if available
        window.addEventListener('load', () => {
            fetch(
                    `../../backend/models/generate_certificate.php?action=load_certificate_layout&training_id=${TRAINING_ID}`
                )
                .then(response => response.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.status && data.data && data.data.canvas_json) {
                            const jsonObj = JSON.parse(data.data.canvas_json);
                            canvas.loadFromJSON(jsonObj, () => {
                                updateNamePlaceholder();
                                canvas.renderAll();
                            });
                        } else {
                            updateNamePlaceholder();
                        }
                    } catch (e) {
                        updateNamePlaceholder();
                    }
                })
                .catch(() => updateNamePlaceholder());
        });
    </script>

    <!-- Minimal Bootstrap JS again if needed -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?= $cspNonce ?>">
    </script>
</body>

</html>
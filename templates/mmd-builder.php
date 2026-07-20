<?php
function shell_arg($rank, $default_value='') {
  global $argv;

  if (isset($argv[$rank])) {
    $v = trim($argv[$rank]);
    if ($v!=='') return $v;
  }

  return $default_value;
}

// get theme name
$theme = shell_arg(1, 'default');

// get mermaid syntax
$gdata = trim(file_get_contents('php://stdin'));
?>
---
config:
  theme: <?php echo "$theme\n"; ?>
  themeVariables:
    fontSize: "12px"
    nodePadding: "6px"
  htmlLabels": false
  useMaxWidth: false
  flowchart:
    useMaxWidth: false
  sequence:
    useMaxWidth: false
  mindmap:
    useMaxWidth: false
  gantt:
    useMaxWidth: false
---
<?php if ($gdata===''): ?>

%% 預設 MMD
flowchart TD
    A[Can it work?]
    B[Did you touch it?]
    C[Does anybody know that?]
    Z1[It's OK! Don't touch it.]
    Z2[Oh! You are such a fool.]
    A --Yes--> Z1
    A --No--> B
    B --No--> Z1
    B --Yes--> C
    C --No--> Z1
    C --Yes--> Z2

<?php else: ?>

%% 自定義 MMD
<?php echo $gdata; ?>

<?php endif; ?>
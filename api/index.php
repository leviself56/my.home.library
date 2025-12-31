<?php
// index.php - Generic API Directory Browser & Tester

// ---------- Config ----------
$ROOT = realpath(__DIR__);

// Auto-detect API base path from current directory
$DOCUMENT_ROOT = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$API_BASE = str_replace($DOCUMENT_ROOT, '', $ROOT);
if (empty($API_BASE)) $API_BASE = '/';

// Configurable exclusions (override via comma-separated env var)
$EXCLUDE = getenv('API_EXCLUDE') 
  ? array_filter(array_map('trim', explode(',', getenv('API_EXCLUDE'))))
  : ['.git', '_files', '_libraries', 'libraries', '_automation'];

// Get requested path (relative)
$rel = $_GET['path'] ?? '';
$rel = trim($rel, "/");

// Resolve safely to an absolute path under ROOT
$abs = realpath($ROOT . ($rel ? DIRECTORY_SEPARATOR . $rel : ''));
if ($abs === false || strncmp($abs, $ROOT, strlen($ROOT)) !== 0) {
  $abs = $ROOT;
  $rel = '';
}

// ---------- Helpers ----------
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function rel_to_abs(string $root, string $rel): string {
  return $rel ? ($root . DIRECTORY_SEPARATOR . $rel) : $root;
}

function list_dirs(string $dir, array $exclude): array {
  $out = [];
  $items = @scandir($dir);
  if ($items === false) return $out;

  foreach ($items as $name) {
    if ($name === '.' || $name === '..') continue;
    if (in_array($name, $exclude, true)) continue;

    $path = $dir . DIRECTORY_SEPARATOR . $name;
    if (is_dir($path)) $out[] = $name;
  }
  sort($out, SORT_NATURAL | SORT_FLAG_CASE);
  return $out;
}

function render_tree(string $rootAbs, string $rootRel, array $exclude, string $apiBase, int $maxDepth = 50): string {
  $lines = [];
  $lines[] = '<span class="tree-dim">.</span>';

  $walk = function(string $abs, string $rel, string $prefix, int $depth) use (&$walk, &$lines, $exclude, $apiBase, $maxDepth) {
    if ($depth >= $maxDepth) return;

    $dirs = list_dirs($abs, $exclude);
    $n = count($dirs);

    foreach ($dirs as $i => $name) {
      $isLast = ($i === $n - 1);
      $branch = $isLast ? '└── ' : '├── ';
      $nextPrefix = $prefix . ($isLast ? '    ' : '│   ');

      $childRel = ltrim(($rel ? $rel . '/' : '') . $name, '/');
      $childAbs = $abs . DIRECTORY_SEPARATOR . $name;
      $hasChildren = !empty(list_dirs($childAbs, $exclude));
      $dirId = 'dir-' . md5($childRel);

      if ($hasChildren) {
        $expandIcon = '<span class="expand-icon">▶</span> ';
        $lines[] = '<div class="tree-item">'
                 . '<span class="tree-dim">' . h($prefix . $branch) . '</span>'
                 . '<span class="tree-dir-toggle" data-target="' . $dirId . '">'
                 . $expandIcon
                 . '<span class="tree-dir-name">' . h($name) . '</span>'
                 . '</span>'
                 . '</div>';
      } else {
        $apiPath = rtrim($apiBase, '/') . '/' . $childRel;
        $docsPath = $childAbs . DIRECTORY_SEPARATOR . 'endpoint.json';
        $docsContent = '';
        if (file_exists($docsPath)) {
          $docsJson = @file_get_contents($docsPath);
          if ($docsJson !== false) {
            $docsContent = h($docsJson);
          }
        }
        
        $lines[] = '<div class="tree-item">'
                 . '<span class="tree-dim">' . h($prefix . $branch) . '</span>'
                 . '<span class="tree-api-endpoint" data-api-path="' . h($apiPath) . '" data-target="' . $dirId . '" data-docs="' . $docsContent . '">'
                 . '<span class="api-icon">⚡</span> '
                 . '<span class="tree-dir-name">' . h($name) . '</span>'
                 . '</span>'
                 . '</div>';
        
        $lines[] = '<div class="api-tester" id="' . $dirId . '" style="display: none;">';
        $lines[] = '  <div class="api-tester-content">';
        $lines[] = '    <div class="api-docs"></div>';
        $lines[] = '    <div class="api-url-bar">';
        $lines[] = '      <span class="api-method-toggle" data-method="GET">GET</span>';
        $lines[] = '      <input type="text" class="api-input" placeholder="Additional path or parameters" value="" />';
        $lines[] = '      <button class="api-run-btn">Run</button>';
        $lines[] = '    </div>';
        $lines[] = '    <div class="api-post-body" style="display: none;">';
        $lines[] = '      <label class="api-post-label">POST Body (JSON):</label>';
        $lines[] = '      <textarea class="api-post-input" placeholder=\'{"key": "value"}\'></textarea>';
        $lines[] = '    </div>';
        $lines[] = '    <div class="api-response" style="display: none;">';
        $lines[] = '      <div class="api-response-header">Response:</div>';
        $lines[] = '      <pre class="api-response-body"></pre>';
        $lines[] = '    </div>';
        $lines[] = '  </div>';
        $lines[] = '</div>';
      }

      if ($hasChildren) {
        $lines[] = '<div class="tree-children" id="' . $dirId . '" style="display: none;">';
        $walk($childAbs, $childRel, $nextPrefix, $depth + 1);
        $lines[] = '</div>';
      }
    }
  };

  $walk($rootAbs, $rootRel, '', 0);
  return implode("\n", $lines);
}

$treeHtml = render_tree($abs, $rel, $EXCLUDE, $API_BASE, 40);

$crumbs = [];
$crumbs[] = ['label' => '.', 'path' => ''];
if ($rel !== '') {
  $parts = explode('/', $rel);
  $acc = '';
  foreach ($parts as $p) {
    $acc = ($acc === '') ? $p : ($acc . '/' . $p);
    $crumbs[] = ['label' => $p, 'path' => $acc];
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>API Directory</title>
  <style>
    html,body{height:100%;margin:0;background:#0b0d10;color:#cbd5e1;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","DejaVu Sans Mono","Courier New",monospace}
    .page{min-height:100%;display:grid;place-items:start center;padding:32px 16px}
    .terminal{width:min(980px,100%);background:#07090c;border:1px solid #1f2937;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.55);overflow:hidden}
    .terminal__bar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;background:linear-gradient(#0f141b,#0b0f15);border-bottom:1px solid #1f2937}
    .dots{display:flex;gap:8px;align-items:center}
    .terminal__dot{width:10px;height:10px;border-radius:999px;background:#334155;opacity:.9}
    .terminal__title{font-size:12px;color:#cbd5e1;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .terminal__title-prefix{color:#a78bfa;margin-right:4px}
    .crumbs{font-size:12px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .crumbs a{color:inherit;text-decoration:none}
    .crumbs a:hover{text-decoration:underline}
    pre{margin:0;padding:16px 18px;font-size:14px;line-height:1.35;white-space:pre;overflow:auto;tab-size:4;text-shadow:0 0 .01px rgba(255,255,255,.2)}
    .tree-dim{color:#6b7280}
    .tree-item{display:inline-block;width:100%}
    .tree-dir-toggle,.tree-api-endpoint{color:#a78bfa;cursor:pointer;font-weight:600;user-select:none}
    .tree-dir-toggle:hover .tree-dir-name,.tree-api-endpoint:hover .tree-dir-name{text-decoration:underline}
    .tree-api-endpoint{color:#34d399}
    .expand-icon,.api-icon{display:inline-block;transition:transform .2s ease;font-size:10px;color:#9ca3af;margin-right:4px}
    .api-icon{color:#34d399;font-size:12px}
    .expanded .expand-icon{transform:rotate(90deg)}
    .tree-children,.api-tester{overflow:hidden;transition:max-height .3s ease}
    .api-tester{margin:8px 0 8px 24px;padding:12px;background:#0f1419;border:1px solid #1f2937;border-radius:6px}
    .api-tester-content{display:flex;flex-direction:column;gap:12px}
    .api-docs{background:#1a1f2e;border:1px solid #2d3748;border-radius:4px;padding:12px;font-size:12px}
    .api-docs-title{color:#34d399;font-weight:700;font-size:13px;margin-bottom:8px}
    .api-docs-description{color:#9ca3af;margin-bottom:12px;line-height:1.5}
    .api-docs-method{margin-bottom:12px;padding:10px;background:#0f1419;border-radius:4px}
    .api-docs-method-header{display:flex;align-items:center;gap:8px;margin-bottom:8px}
    .api-docs-method-badge{background:#059669;color:#fff;padding:3px 8px;border-radius:3px;font-size:10px;font-weight:700}
    .api-docs-method-badge.post{background:#f59e0b}
    .api-docs-method-desc{color:#cbd5e1;font-size:11px}
    .api-docs-params{margin-top:8px}
    .api-docs-params-title{color:#a78bfa;font-size:11px;font-weight:600;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
    .api-docs-param{padding:6px 8px;background:#1a1f2e;border-radius:3px;margin-bottom:4px}
    .api-docs-param-name{color:#60a5fa;font-weight:600;font-size:11px}
    .api-docs-param-required{color:#ef4444;font-size:9px;font-weight:700;text-transform:uppercase;margin-left:4px}
    .api-docs-param-type{color:#a78bfa;font-size:10px;margin-left:4px}
    .api-docs-param-desc{color:#9ca3af;font-size:10px;margin-top:2px}
    .api-docs-example{margin-top:8px;font-size:10px}
    .api-docs-example-title{color:#9ca3af;font-weight:600;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px}
    .api-docs-example-code{background:#0b0f15;border:1px solid #1f2937;border-radius:3px;padding:6px 8px;color:#34d399;font-size:10px;overflow-x:auto}
    .api-url-bar{display:flex;gap:8px;align-items:center}
    .api-method-toggle{background:#059669;color:#fff;padding:6px 10px;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:.5px;cursor:pointer;user-select:none;transition:background .2s;min-width:45px;text-align:center}
    .api-method-toggle:hover{background:#047857}
    .api-method-toggle[data-method="POST"]{background:#f59e0b}
    .api-method-toggle[data-method="POST"]:hover{background:#d97706}
    .api-input{flex:1;background:#1f2937;border:1px solid #374151;color:#cbd5e1;padding:6px 10px;border-radius:4px;font-family:inherit;font-size:13px}
    .api-input:focus{outline:0;border-color:#34d399}
    .api-post-body{display:flex;flex-direction:column;gap:6px}
    .api-post-label{color:#9ca3af;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
    .api-post-input{background:#1f2937;border:1px solid #374151;color:#cbd5e1;padding:8px 10px;border-radius:4px;font-family:inherit;font-size:13px;min-height:80px;resize:vertical}
    .api-post-input:focus{outline:0;border-color:#f59e0b}
    .api-run-btn{background:#3b82f6;color:#fff;border:none;padding:6px 16px;border-radius:4px;font-weight:600;font-size:13px;cursor:pointer;transition:background .2s}
    .api-run-btn:hover{background:#2563eb}
    .api-run-btn:active{background:#1d4ed8}
    .api-response{margin-top:8px}
    .api-response-header{color:#9ca3af;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
    .api-response-body{background:#0b0f15;border:1px solid #1f2937;border-radius:4px;padding:12px;margin:0;font-size:12px;line-height:1.5;color:#cbd5e1;max-height:400px;overflow:auto}
    .api-loading{color:#3b82f6;font-style:italic}
    .api-error{color:#ef4444}
    .json-key{color:#a78bfa;font-weight:600}
    .json-string{color:#34d399}
    .json-number{color:#60a5fa}
    .json-boolean{color:#f59e0b}
    .json-null{color:#94a3b8;font-style:italic}
    pre::-webkit-scrollbar{height:10px;width:10px}
    pre::-webkit-scrollbar-thumb{background:#1f2937;border-radius:999px}
    pre::-webkit-scrollbar-track{background:#0b0f15}
    ::selection{background:rgba(99,102,241,.35)}
  </style>
</head>
<body>
  <div class="page">
    <div class="terminal">
      <div class="terminal__bar">
        <div class="dots">
          <span class="terminal__dot"></span>
          <span class="terminal__dot"></span>
          <span class="terminal__dot"></span>
        </div>
        <div class="terminal__title">
          <span class="terminal__title-prefix">API Browser:</span>
          <?php
            $displayPath = $rel ? '/' . $rel : $API_BASE;
            echo h($displayPath);
          ?>
        </div>
        <div class="crumbs">
          <?php
            $out = [];
            foreach ($crumbs as $c) {
              $href = $c['path'] === '' ? '?' : rawurlencode($c['path']);
              $out[] = '<a href="' . h($href) . '">' . h($c['label']) . '</a>';
            }
            echo implode(' <span class="tree-dim">/</span> ', $out);
          ?>
        </div>
      </div>
      <pre><?php echo $treeHtml; ?></pre>
    </div>
  </div>
  <script>
function renderApiDocs(docs){let html='';html+='<div class="api-docs-title">'+escapeHtml(docs.name)+'</div>';if(docs.description){html+='<div class="api-docs-description">'+escapeHtml(docs.description)+'</div>'}if(docs.methods&&docs.methods.length>0){docs.methods.forEach(function(method){html+='<div class="api-docs-method">';html+='<div class="api-docs-method-header">';html+='<span class="api-docs-method-badge '+method.type.toLowerCase()+'">'+method.type+'</span>';html+='<span class="api-docs-method-desc">'+escapeHtml(method.description)+'</span>';html+='</div>';if(method.parameters&&method.parameters.length>0){html+='<div class="api-docs-params">';html+='<div class="api-docs-params-title">Parameters</div>';method.parameters.forEach(function(param){html+='<div class="api-docs-param">';html+='<span class="api-docs-param-name">'+escapeHtml(param.name)+'</span>';if(param.required){html+='<span class="api-docs-param-required">Required</span>'}html+='<span class="api-docs-param-type">('+escapeHtml(param.type)+')</span>';html+='<div class="api-docs-param-desc">'+escapeHtml(param.description)+'</div>';html+='</div>'});html+='</div>'}if(method.example){html+='<div class="api-docs-example">';html+='<div class="api-docs-example-title">Example</div>';if(method.example.url){html+='<div class="api-docs-example-code">'+escapeHtml(method.example.url)+'</div>'}if(method.example.body){html+='<div class="api-docs-example-title" style="margin-top:8px;">Body</div>';html+='<div class="api-docs-example-code">'+escapeHtml(JSON.stringify(method.example.body,null,2))+'</div>'}html+='</div>'}html+='</div>'})}return html}
function escapeHtml(text){const div=document.createElement('div');div.textContent=text;return div.innerHTML}
function syntaxHighlight(json){if(typeof json!=='string'){json=JSON.stringify(json,null,2)}json=json.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,function(match){let cls='json-number';if(/^"/.test(match)){if(/:$/.test(match)){cls='json-key'}else{cls='json-string'}}else if(/true|false/.test(match)){cls='json-boolean'}else if(/null/.test(match)){cls='json-null'}return '<span class="'+cls+'">'+match+'</span>'})}
document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.tree-dir-toggle').forEach(function(toggle){toggle.addEventListener('click',function(e){const targetId=this.getAttribute('data-target');const target=document.getElementById(targetId);if(target){const isExpanded=target.style.display!=='none';if(isExpanded){target.style.display='none';this.classList.remove('expanded')}else{target.style.display='block';this.classList.add('expanded')}}})});document.querySelectorAll('.tree-api-endpoint').forEach(function(endpoint){endpoint.addEventListener('click',function(e){const targetId=this.getAttribute('data-target');const target=document.getElementById(targetId);if(target){const isExpanded=target.style.display!=='none';if(isExpanded){target.style.display='none'}else{target.style.display='block';const docsContainer=target.querySelector('.api-docs');if(docsContainer&&!docsContainer.hasAttribute('data-loaded')){const docsJson=this.getAttribute('data-docs');if(docsJson){try{const docs=JSON.parse(docsJson);docsContainer.innerHTML=renderApiDocs(docs);docsContainer.setAttribute('data-loaded','true')}catch(e){docsContainer.innerHTML='<span style="color:#94a3b8;font-style:italic;">No documentation available</span>'}}else{docsContainer.innerHTML='<span style="color:#94a3b8;font-style:italic;">No documentation available</span>'}}}}})});document.querySelectorAll('.api-tester').forEach(function(tester){const runBtn=tester.querySelector('.api-run-btn');const input=tester.querySelector('.api-input');const responseDiv=tester.querySelector('.api-response');const responseBody=tester.querySelector('.api-response-body');const endpoint=tester.previousElementSibling.querySelector('.tree-api-endpoint');const methodToggle=tester.querySelector('.api-method-toggle');const postBodyDiv=tester.querySelector('.api-post-body');const postBodyInput=tester.querySelector('.api-post-input');methodToggle.addEventListener('click',function(){const currentMethod=this.getAttribute('data-method');if(currentMethod==='GET'){this.setAttribute('data-method','POST');this.textContent='POST';postBodyDiv.style.display='flex'}else{this.setAttribute('data-method','GET');this.textContent='GET';postBodyDiv.style.display='none'}});runBtn.addEventListener('click',async function(){const apiPath=endpoint.getAttribute('data-api-path');const additionalPath=input.value.trim();const fullUrl=apiPath+'/'+(additionalPath||'');const method=methodToggle.getAttribute('data-method');responseDiv.style.display='block';responseBody.textContent='Loading...\nURL: '+fullUrl+'\nMethod: '+method;responseBody.className='api-response-body api-loading';runBtn.textContent='Running...';runBtn.disabled=true;try{const fetchOptions={method:method,headers:{'Content-Type':'application/json'}};if(method==='POST'&&postBodyInput.value.trim()){try{JSON.parse(postBodyInput.value);fetchOptions.body=postBodyInput.value}catch(e){throw new Error('Invalid JSON in POST body: '+e.message)}}const response=await fetch(fullUrl,fetchOptions);const contentType=response.headers.get('content-type');let data;let output='';output+=`Status: ${response.status} ${response.statusText}\n`;output+=`Method: ${method}\n`;output+=`URL: ${fullUrl}\n`;if(method==='POST'&&fetchOptions.body){output+=`\nPOST Body:\n${fetchOptions.body}\n`}output+='─'.repeat(60)+'\n\n';if(contentType&&contentType.includes('application/json')){data=await response.json();const jsonText=JSON.stringify(data,null,2);const highlighted=syntaxHighlight(jsonText);responseBody.innerHTML=output+highlighted}else{data=await response.text();output+=data;responseBody.textContent=output}responseBody.className='api-response-body'+(response.ok?'':' api-error')}catch(error){let errorOutput=`Error: ${error.message}\nMethod: ${method}\nURL: ${fullUrl}\n`;if(method==='POST'&&postBodyInput.value.trim()){errorOutput+=`\nPOST Body:\n${postBodyInput.value}\n`}responseBody.textContent=errorOutput;responseBody.className='api-response-body api-error'}finally{runBtn.textContent='Run';runBtn.disabled=false}});input.addEventListener('keypress',function(e){if(e.key==='Enter'){runBtn.click()}})});document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key==='e'){e.preventDefault();document.querySelectorAll('.tree-children').forEach(el=>el.style.display='block');document.querySelectorAll('.tree-dir-toggle').forEach(el=>el.classList.add('expanded'))}if((e.ctrlKey||e.metaKey)&&e.shiftKey&&e.key==='E'){e.preventDefault();document.querySelectorAll('.tree-children').forEach(el=>el.style.display='none');document.querySelectorAll('.tree-dir-toggle').forEach(el=>el.classList.remove('expanded'));document.querySelectorAll('.api-tester').forEach(el=>el.style.display='none')}})});
  </script>
</body>
</html>
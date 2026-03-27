/* =============================================
   DocuScan — Frontend Application Logic
   ============================================= */

(function () {
  'use strict';

  // ---- DOM Helper ----
  const $ = id => document.getElementById(id);

  // ---- DOM refs ----
  const dropZone       = $('drop-zone');
  const fileInput      = $('file-input');
  const browseLink     = $('browse-link');
  const filePreview    = $('file-preview');
  const fileNameEl     = $('file-name');
  const fileSizeEl     = $('file-size');
  const fileRemoveBtn  = $('file-remove');
  const uploadBtn      = $('upload-btn');
  const progressSec    = $('progress-section');
  const progressBar    = $('progress-bar');
  const progressLabel  = $('progress-label');
  const resultsSec     = $('results-section');
  const tryAgainBtn    = $('try-again-btn');

  const steps = [
    $('step-1'), $('step-2'), $('step-3'), $('step-4')
  ];

  let selectedFile = null;

  // ---- Drop Zone Logic ----
  if (dropZone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
      dropZone.addEventListener(evt, e => {
        e.preventDefault();
        e.stopPropagation();
      });
    });

    dropZone.addEventListener('dragenter', () => dropZone.classList.add('drag-active'));
    dropZone.addEventListener('dragover',  () => dropZone.classList.add('drag-active'));
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-active'));
    dropZone.addEventListener('drop', e => {
      dropZone.classList.remove('drag-active');
      handleFiles(e.dataTransfer.files);
    });
  }

  if (browseLink) browseLink.onclick = () => fileInput && fileInput.click();
  if (fileInput) fileInput.onchange = e => handleFiles(e.target.files);

  function handleFiles(files) {
    if (!files || files.length === 0) return;
    const file = files[0];
    if (file.type !== 'application/pdf') {
      alert('Invalid file type. Please upload a PDF.');
      return;
    }
    selectedFile = file;
    if (fileNameEl) fileNameEl.textContent = file.name;
    if (fileSizeEl) fileSizeEl.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
    if (dropZone) dropZone.classList.add('hidden');
    if (filePreview) filePreview.classList.remove('hidden');
    if (uploadBtn) uploadBtn.disabled = false;
  }

  function clearFile() {
    selectedFile = null;
    if (fileInput) fileInput.value = '';
    if (filePreview) filePreview.classList.add('hidden');
    if (dropZone) dropZone.classList.remove('hidden');
    if (uploadBtn) uploadBtn.disabled = true;
    resetSteps();
  }

  if (fileRemoveBtn) fileRemoveBtn.addEventListener('click', clearFile);

  function resetSteps() {
    steps.forEach(s => { if(s) s.classList.remove('active', 'done'); });
    if (progressBar) progressBar.style.width = '0%';
  }

  function activateStep(idx) {
    steps.forEach((s, i) => {
      if (!s) return;
      if (i < idx)  { s.classList.remove('active'); s.classList.add('done'); }
      else if (i === idx) { s.classList.add('active'); s.classList.remove('done'); }
      else { s.classList.remove('active', 'done'); }
    });
  }

  function setProgress(pct, label) {
    if (progressBar) progressBar.style.width = pct + '%';
    if (progressLabel && label) progressLabel.textContent = label;
  }

  function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // ---- Render Functions ----
  function renderResults(data) {
    console.log("Rendering results...", data);
    if (progressSec) progressSec.classList.add('hidden');
    if (resultsSec)  resultsSec.classList.remove('hidden');

    // Hide input UI for cleaner results view
    const reqCard = document.querySelector('.requirements-card');
    const upCard  = document.querySelector('.upload-card');
    if (reqCard) reqCard.classList.add('hidden');
    if (upCard)  upCard.classList.add('hidden');

    const statusBanner = $('status-banner');
    const tabsList     = $('tabs-list');
    const tabContent   = $('tab-content');
    const resultsCard  = $('results-tabs-card');
    const breakdownCard = $('breakdown-card');
    const pageBreakdown = $('page-breakdown');

    // 1. Status Banner
    if (statusBanner) {
      if (data.status === 'pending') {
        statusBanner.className = 'status-banner pending';
        statusBanner.innerHTML = `<h3>✅ Verification Complete</h3><p>We've successfully processed all pages. Detailed breakdown below.</p>`;
      } else {
        statusBanner.className = 'status-banner error';
        statusBanner.innerHTML = `<h3>⚠️ Document Incomplete</h3><p>We detected issues with your submission. Please review the breakdown below.</p>`;
      }
    }

    // 2. Master Data Table
    if (breakdownCard && pageBreakdown) {
      // Preference list for important columns
    const commonLabels = { 
      given_name: 'Given Name', last_name: 'Last Name', middle_name: 'Middle Name', 
      dob: 'DOB', age: 'Age', full_name: 'Full Name', id_type: 'ID Type', 
      id_number: 'ID Number', expiration_date: 'Expiration Date', description: 'Description'
    };
    
    // Find ALL unique keys present across all pages (excluding raw_text)
    let allFoundKeys = new Set();
    data.pages.forEach(p => {
      if (p.metadata) {
        Object.keys(p.metadata).forEach(k => {
          if (k !== 'raw_text' && p.metadata[k] && p.metadata[k] !== 'Not Detected') {
            allFoundKeys.add(k);
          }
        });
      }
    });

    // Sort keys: favored ones first, then others alphabetically
    const activeCols = Array.from(allFoundKeys).sort((a, b) => {
      const aIdx = Object.keys(commonLabels).indexOf(a);
      const bIdx = Object.keys(commonLabels).indexOf(b);
      if (aIdx !== -1 && bIdx !== -1) return aIdx - bIdx;
      if (aIdx !== -1) return -1;
      if (bIdx !== -1) return 1;
      return a.localeCompare(b);
    });

    const getLabel = key => commonLabels[key] || key.replace(/_/g, ' ').toUpperCase();

      breakdownCard.style.display = 'block';
      pageBreakdown.innerHTML = `
        <div class="table-scroll">
          <table class="metadata-table master-table">
            <thead>
              <tr>
                <th>Page</th>
                <th>Status</th>
                ${activeCols.map(key => `<th>${getLabel(key)}</th>`).join('')}
              </tr>
            </thead>
            <tbody>
              ${data.pages.map(p => {
                const meta = p.metadata || {};
                return `
                  <tr>
                    <td><div class="page-link-cell"><strong>Page ${p.page}</strong><span class="type-sub">${p.type.toUpperCase()}</span></div></td>
                    <td><span class="status-pill ${p.valid ? 'pass' : 'fail'}">${p.valid ? 'Valid' : 'Invalid'}</span></td>
                    ${activeCols.map(key => `<td class="${meta[key] ? '' : 'muted'} ${key === 'id_type' ? 'id-type-highlight' : ''}">${escHtml(meta[key] || '-')}</td>`).join('')}
                  </tr>`;
              }).join('')}
            </tbody>
          </table>
        </div>`;
    }

    // 3. Tabbed Details
    if (resultsCard && tabsList) {
      resultsCard.classList.remove('hidden');
      tabsList.innerHTML = data.pages.map((p, i) => `
        <button class="tab-btn ${i === 0 ? 'active' : ''} ${p.valid ? '' : 'invalid-tab'}" data-page="${p.page}">
          Page ${p.page}
        </button>`).join('');

      tabsList.querySelectorAll('.tab-btn').forEach(btn => {
        btn.onclick = () => {
          tabsList.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          switchTab(btn.dataset.page, data, tabContent);
        };
      });
      
      // Default to first tab
      if (data.pages.length > 0) switchTab(data.pages[0].page, data, tabContent);
    }
  }

  function switchTab(pageNum, data, tabContent) {
    if (!tabContent) return;
    const pageData = data.pages.find(p => p.page == pageNum);
    if (!pageData) return;

    const labels = { 
      given_name: 'Given Name', last_name: 'Last Name', middle_name: 'Middle Name', 
      dob: 'DOB', age: 'Age', id_type: 'ID Type', id_number: 'ID Number', 
      full_name: 'Full Name', expiration_date: 'Expiration Date' 
    };
    const metadata = pageData.metadata || {};
    const metaKeys = Object.keys(metadata).filter(k => k !== 'raw_text' && metadata[k] !== null && metadata[k] !== undefined);
    
    let metaHtml = metaKeys.length > 0 
      ? `<div class="data-section"><h4>📋 Extracted Metadata</h4><table class="metadata-table"><thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>
          ${metaKeys.map(k => `<tr><td class="meta-label">${labels[k] || k.replace(/_/g, ' ').toUpperCase()}</td><td class="meta-val">${escHtml(metadata[k])}</td></tr>`).join('')}
         </tbody></table></div>`
      : `<p class="empty-msg">No structured data extracted.</p>`;

    const issuesHtml = pageData.issues.length > 0
      ? `<div class="data-section"><h4>🚩 Validation Status</h4><ul class="missing-list">${pageData.issues.map(iss => `<li class="error-li">${escHtml(iss)}</li>`).join('')}</ul></div>`
      : `<div class="data-section"><h4>✅ Status</h4><p class="pass-msg">Page passed all checks.</p></div>`;

    const isMissing = !pageData.image_path;
    tabContent.innerHTML = `
      <div class="page-detail-layout ${isMissing ? 'page-missing-layout' : ''}">
        <div class="page-preview-side">
          <div class="page-preview-box ${isMissing ? 'missing' : ''}">
             ${isMissing ? '<div class="missing-placeholder"><span>Missing</span></div>' : `<img src="${pageData.image_path}" alt="Page ${pageData.page}" />`}
          </div>
        </div>
        <div class="page-data-side">
          ${isMissing ? `<div class="missing-hero"><h3>⚠️ Page Not Found</h3><p>Required page not detected.</p></div>` : `${metaHtml}${issuesHtml}<div class="data-section"><h4>📄 OCR Text Insight</h4><div class="raw-text-display">${escHtml(pageData.text_preview || 'No text detected.')}</div></div>`}
        </div>
      </div>`;
  }

  function uploadFile(file) {
    if (resultsSec) resultsSec.classList.add('hidden');
    if (progressSec) progressSec.classList.remove('hidden');
    resetSteps();
    activateStep(0);
    setProgress(5, 'Preparing document…');

    const formData = new FormData();
    formData.append('pdf', file);
    formData.append('engine', 'gemini');

    const xhr = new XMLHttpRequest();
    xhr.upload.addEventListener('progress', e => {
      if (e.lengthComputable) {
        const pct = Math.round((e.loaded / e.total) * 40);
        setProgress(pct, 'Uploading…');
        if (pct >= 40) activateStep(1);
      }
    });

    xhr.onreadystatechange = () => {
      if (xhr.readyState === 4) {
        if (xhr.status === 200) {
          try {
            const data = JSON.parse(xhr.responseText);
            if (data.error) { alert(data.error); clearFile(); } 
            else { activateStep(4); setProgress(100, 'Complete!'); setTimeout(() => renderResults(data), 500); }
          } catch (e) { alert('Invalid server response.'); clearFile(); }
        } else { alert('Verification failed (Server Error 500).'); clearFile(); }
      }
    };

    setTimeout(() => { if (xhr.readyState < 4) { activateStep(2); setProgress(60, 'AI Extraction…'); } }, 3000);
    setTimeout(() => { if (xhr.readyState < 4) { activateStep(3); setProgress(85, 'Finalizing…'); } }, 8000);

    xhr.open('POST', 'upload.php');
    xhr.send(formData);
  }

  if (uploadBtn) uploadBtn.onclick = () => selectedFile && uploadFile(selectedFile);
  
  const expandReportBtn = $('expand-report-btn');
  const breakdownCard   = $('breakdown-card');
  if (expandReportBtn && breakdownCard) {
    expandReportBtn.onclick = () => {
      breakdownCard.classList.toggle('expanded');
      expandReportBtn.innerHTML = breakdownCard.classList.contains('expanded') ? '✖' : '⛶';
      document.body.style.overflow = breakdownCard.classList.contains('expanded') ? 'hidden' : '';
    };
  }
  
  if (tryAgainBtn) tryAgainBtn.onclick = () => { 
    clearFile(); 
    if (resultsSec) resultsSec.classList.add('hidden'); 
    if (breakdownCard) breakdownCard.classList.remove('expanded');
    if (expandReportBtn) expandReportBtn.innerHTML = '⛶';
    document.body.style.overflow = '';
    
    // Show input UI again
    const reqCard = document.querySelector('.requirements-card');
    const upCard  = document.querySelector('.upload-card');
    if (reqCard) reqCard.classList.remove('hidden');
    if (upCard)  upCard.classList.remove('hidden');
    
    window.scrollTo({ top: 0, behavior: 'smooth' }); 
  };

})();

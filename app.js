/* =============================================
   DocuScan — Frontend Application Logic
   ============================================= */

(function () {
  'use strict';

  // ---- DOM refs ----
  const dropZone       = document.getElementById('drop-zone');
  const fileInput      = document.getElementById('file-input');
  const browseLink     = document.getElementById('browse-link');
  const filePreview    = document.getElementById('file-preview');
  const fileNameEl     = document.getElementById('file-name');
  const fileSizeEl     = document.getElementById('file-size');
  const fileRemoveBtn  = document.getElementById('file-remove');
  const uploadBtn      = document.getElementById('upload-btn');
  const progressSec    = document.getElementById('progress-section');
  const progressBar    = document.getElementById('progress-bar');
  const progressLabel  = document.getElementById('progress-label');
  const resultsSec     = document.getElementById('results-section');
  const statusBanner   = document.getElementById('status-banner');
  const missingCard    = document.getElementById('missing-card');
  const missingList    = document.getElementById('missing-list');
  const breakdownCard  = document.getElementById('breakdown-card');
  const pageBreakdown  = document.getElementById('page-breakdown');
  const tryAgainBtn    = document.getElementById('try-again-btn');

  const steps = [
    document.getElementById('step-1'),
    document.getElementById('step-2'),
    document.getElementById('step-3'),
    document.getElementById('step-4'),
  ];

  let selectedFile = null;

  // ---- File Size Formatter ----
  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
  }

  // ---- File Selection ----
  function selectFile(file) {
    if (!file) return;

    // Client-side type check
    if (file.type !== 'application/pdf') {
      showClientError('Only PDF files are accepted. Please select a valid PDF.');
      return;
    }
    // Client-side size check (20 MB)
    if (file.size > 20 * 1024 * 1024) {
      showClientError('File is too large. Maximum allowed size is 20 MB.');
      return;
    }

    selectedFile = file;
    fileNameEl.textContent = file.name;
    fileSizeEl.textContent = formatBytes(file.size);
    filePreview.classList.remove('hidden');
    uploadBtn.disabled = false;
    hideResults();
  }

  function clearFile() {
    selectedFile = null;
    fileInput.value = '';
    filePreview.classList.add('hidden');
    uploadBtn.disabled = true;
    hideResults();
  }

  function showClientError(msg) {
    resultsSec.classList.remove('hidden');
    progressSec.classList.add('hidden');
    statusBanner.className = 'status-banner error';
    statusBanner.innerHTML = `
      <span class="status-icon">⚠</span>
      <div class="status-text-wrap">
        <span class="status-title">Invalid File</span>
        <span class="status-sub">${escHtml(msg)}</span>
      </div>`;
    missingCard.classList.add('hidden');
    breakdownCard.style.display = 'none';
  }

  function hideResults() {
    resultsSec.classList.add('hidden');
    progressSec.classList.add('hidden');
    resetSteps();
  }

  // ---- Drag & Drop ----
  dropZone.addEventListener('click', () => fileInput.click());
  browseLink.addEventListener('click', (e) => { e.stopPropagation(); fileInput.click(); });
  dropZone.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') fileInput.click(); });

  fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) selectFile(fileInput.files[0]);
  });

  dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('drag-over');
  });
  dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
  dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) selectFile(file);
  });

  fileRemoveBtn.addEventListener('click', clearFile);

  // ---- Progress Steps ----
  function resetSteps() {
    steps.forEach(s => { s.classList.remove('active', 'done'); });
    progressBar.style.width = '0%';
  }

  function activateStep(idx) {
    steps.forEach((s, i) => {
      if (i < idx)  { s.classList.remove('active'); s.classList.add('done'); }
      else if (i === idx) { s.classList.add('active'); s.classList.remove('done'); }
      else { s.classList.remove('active', 'done'); }
    });
  }

  function setProgress(pct, label) {
    progressBar.style.width = pct + '%';
    if (label) progressLabel.textContent = label;
  }

  // ---- HTML Escape ----
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // ---- Render Results ----
  function renderResults(data) {
    progressSec.classList.add('hidden');
    resultsSec.classList.remove('hidden');

    const status = data.status; // 'pending' | 'incomplete' | 'error'

    // Status banner
    if (status === 'pending') {
      statusBanner.className = 'status-banner pending';
      statusBanner.innerHTML = `
        <span class="status-icon">✅</span>
        <div class="status-text-wrap">
          <span class="status-title">PENDING</span>
          <span class="status-sub">All ${data.pages.length} required pages are complete. Your document has been submitted successfully.</span>
        </div>`;
      missingCard.classList.add('hidden');
    } else if (status === 'incomplete') {
      statusBanner.className = 'status-banner incomplete';
      statusBanner.innerHTML = `
        <span class="status-icon">❌</span>
        <div class="status-text-wrap">
          <span class="status-title">INCOMPLETE</span>
          <span class="status-sub">Your document is missing or has failed ${data.missing.length} requirement(s). Please review the details below.</span>
        </div>`;

      // Missing list
      missingCard.classList.remove('hidden');
      missingList.innerHTML = data.missing.map((m, i) =>
        `<li style="animation-delay:${i * 0.06}s">${escHtml(m)}</li>`
      ).join('');
    } else {
      statusBanner.className = 'status-banner error';
      statusBanner.innerHTML = `
        <span class="status-icon">⚠</span>
        <div class="status-text-wrap">
          <span class="status-title">Processing Error</span>
          <span class="status-sub">${escHtml(data.message || 'An unexpected error occurred.')}</span>
        </div>`;
      missingCard.classList.add('hidden');
    }

    // Page breakdown
    if (data.pages && data.pages.length > 0) {
      breakdownCard.style.display = '';
      const typeLabels = { form: 'Form', id_picture: 'ID Picture', documentary: 'Documentary' };
      const typeBadge  = { form: 'badge-form', id_picture: 'badge-id', documentary: 'badge-doc' };

      pageBreakdown.innerHTML = data.pages.map((p, i) => {
        const valid   = p.valid;
        const label   = typeLabels[p.type] || p.type;
        const badgeCls = typeBadge[p.type] || 'badge-form';
        const issues  = p.issues || [];

        const issueHtml = issues.length
          ? issues.map(iss => `<span class="page-issue">✕ ${iss}</span>`).join('')
          : `<span class="page-ok">✓ Passed validation</span>`;

        return `
          <div class="page-item ${valid ? 'valid' : 'invalid'}" style="animation-delay:${i * 0.07}s">
            <span class="page-num">Pg ${p.page}</span>
            <div class="page-info">
              <span class="page-label">
                Page ${p.page}
                <span class="page-type-badge ${badgeCls}">${label}</span>
              </span>
              <div class="page-issues">${issueHtml}</div>
            </div>
            <span class="page-status-icon">${valid ? '✅' : '❌'}</span>
          </div>`;
      }).join('');
    } else {
      breakdownCard.style.display = 'none';
    }

    // Scroll to results
    setTimeout(() => {
      resultsSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
  }

  // ---- Upload ----
  uploadBtn.addEventListener('click', () => {
    if (!selectedFile) return;

    // Show progress
    hideResults();
    progressSec.classList.remove('hidden');
    uploadBtn.disabled = true;
    resetSteps();
    activateStep(0);
    setProgress(5, 'Uploading PDF…');

    const formData = new FormData();
    formData.append('pdf', selectedFile);

    const xhr = new XMLHttpRequest();

    // Upload progress
    xhr.upload.addEventListener('progress', (e) => {
      if (e.lengthComputable) {
        const pct = Math.round((e.loaded / e.total) * 40); // 0–40%
        setProgress(pct, 'Uploading PDF…');
      }
    });

    xhr.upload.addEventListener('load', () => {
      setProgress(42, 'Converting PDF pages…');
      activateStep(1);
    });

    xhr.addEventListener('load', () => {
      try {
        const data = JSON.parse(xhr.responseText);

        if (data.error) {
          progressSec.classList.add('hidden');
          resultsSec.classList.remove('hidden');
          statusBanner.className = 'status-banner error';
          statusBanner.innerHTML = `
            <span class="status-icon">⚠</span>
            <div class="status-text-wrap">
              <span class="status-title">Upload Error</span>
              <span class="status-sub">${escHtml(data.error)}</span>
            </div>`;
          missingCard.classList.add('hidden');
          breakdownCard.style.display = 'none';
          uploadBtn.disabled = false;
          return;
        }

        // Simulate step progress for UX (processing already done server-side)
        setProgress(65, 'Running OCR scan…');
        activateStep(2);

        setTimeout(() => {
          setProgress(88, 'Validating pages…');
          activateStep(3);
        }, 600);

        setTimeout(() => {
          setProgress(100, 'Complete!');
          steps.forEach(s => { s.classList.remove('active'); s.classList.add('done'); });

          setTimeout(() => renderResults(data), 400);
        }, 1200);

      } catch (e) {
        showClientError('Unexpected server response. Please try again.');
        uploadBtn.disabled = false;
      }
    });

    xhr.addEventListener('error', () => {
      showClientError('Network error. Please check your connection and try again.');
      uploadBtn.disabled = false;
      progressSec.classList.add('hidden');
    });

    xhr.open('POST', 'upload.php');
    xhr.send(formData);
  });

  // ---- Try Again ----
  tryAgainBtn.addEventListener('click', () => {
    clearFile();
    hideResults();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

})();

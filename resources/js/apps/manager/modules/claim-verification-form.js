export function bindClaimVerificationForm() {
    const form = document.querySelector('form[data-claim-verification-form]');
    if (!form) return;

    const approveButton = form.querySelector('[data-approve-btn]');
    const approveHint = form.querySelector('[data-approve-hint]');
    const autofillBtn = document.getElementById('btn-fill-ai') || document.getElementById('btn-autofill-ai');
    const resetBtn = document.getElementById('btn-reset-checklist');
    const aiBanner = document.getElementById('checklist-ai-banner');
    const aiBannerText = document.getElementById('checklist-ai-banner-text');
    const rejectBtn = form.querySelector('button[formaction*="reject"], .claim-action-btn.danger');
    const notesInput = form.querySelector('textarea[name="catatan_verifikasi_admin"]');
    const reasonInput = form.querySelector('textarea[name="alasan_penolakan"]');

    const checklistKeys = [
        'identitas_pelapor_valid',
        'detail_barang_valid',
        'kronologi_valid',
        'bukti_visual_valid',
        'kecocokan_data_laporan'
    ];

    const physicalAiKeys = [
        'detail_barang_valid',
        'bukti_visual_valid',
        'kecocokan_data_laporan'
    ];

    /**
     * Get current AI score from DOM or dataset
     * @returns {number|null}
     */
    function getAiScore() {
        const scoreElement = document.getElementById('ai-score-display');
        if (scoreElement && scoreElement.textContent) {
            const parsed = parseInt(scoreElement.textContent.trim(), 10);
            if (!isNaN(parsed)) {
                return parsed;
            }
        }
        const btn = document.getElementById('btn-fill-ai') || document.getElementById('btn-autofill-ai');
        if (btn && btn.dataset.aiScore) {
            const parsed = parseInt(btn.dataset.aiScore, 10);
            if (!isNaN(parsed)) {
                return parsed;
            }
        }
        return null;
    }

    /**
     * Check if AI reasoning text mentions visual similarity
     * @returns {boolean}
     */
    function isVisualReasoningSimilar() {
        const reasoningElement = document.getElementById('ai-reasoning-text');
        const reasoning = (reasoningElement ? reasoningElement.textContent : '').toLowerCase();
        if (!reasoning) return false;

        // If explicitly negative about visual:
        const hasNegative = /(?:visual|foto|gambar)\s*(?:barang)?\s*(?:tidak|bukan|kurang)\s*(?:mirip|identik|sesuai|cocok)/.test(reasoning)
            || /(?:tidak|bukan|kurang)\s*(?:mirip|identik|sesuai|cocok)\s*(?:secara)?\s*(?:visual|foto|gambar)/.test(reasoning)
            || /(?:visual|foto|gambar)\s*(?:berbeda|kontras|tidak ada kesamaan)/.test(reasoning);

        if (hasNegative) {
            return false;
        }

        // Positive visual cues:
        const hasPositive = /(?:visual|foto|gambar)[\s\w]*(?:tampak|sangat|cukup)?\s*(?:identik|mirip|sesuai|cocok|sama)/.test(reasoning)
            || /(?:tampak|sangat)?\s*(?:identik|mirip|sesuai|cocok)\s*(?:secara)?\s*(?:visual|foto|gambar)/.test(reasoning)
            || /tampak identik|tampak mirip|secara visual mirip|secara visual identik/.test(reasoning);

        return hasPositive;
    }

    /**
     * Set a specific checklist item value (1 for Ya, 0 for Tidak)
     */
    function setChecklistValue(key, val) {
        const radio = form.querySelector(`input[name="${key}"][value="${val}"]`);
        if (radio) {
            radio.checked = true;
        }
        const select = form.querySelector(`select[name="${key}"]`);
        if (select) {
            select.value = String(val);
        }
    }

    /**
     * Update active visual classes on pill labels based on checked input
     */
    function updatePillVisuals() {
        form.querySelectorAll('.checklist-pill-btn').forEach(btn => {
            const input = btn.querySelector('input[type="radio"]');
            if (!input) return;
            const isChecked = input.checked;
            btn.classList.toggle('is-active', isChecked);
            if (input.value === '1') {
                btn.classList.toggle('is-yes', isChecked);
            } else if (input.value === '0') {
                btn.classList.toggle('is-no', isChecked);
            }
        });
    }

    /**
     * Compute completion, AI score eligibility, and toggle approve button availability
     */
    function updateSubmitButtonState() {
        updatePillVisuals();

        let missingCount = 0;
        let allYes = true;
        let hasAnyNo = false;

        checklistKeys.forEach(key => {
            const checkedRadio = form.querySelector(`input[name="${key}"]:checked`);
            if (checkedRadio) {
                if (checkedRadio.value !== '1') {
                    allYes = false;
                    hasAnyNo = true;
                }
            } else {
                // Fallback for select element if present
                const select = form.querySelector(`select[name="${key}"]`);
                if (select && select.value !== '') {
                    if (select.value !== '1') {
                        allYes = false;
                        hasAnyNo = true;
                    }
                } else {
                    missingCount++;
                    allYes = false;
                }
            }
        });

        const isComplete = (missingCount === 0);
        const aiScore = getAiScore();
        const isAiHigh = (aiScore === null || isNaN(aiScore) || aiScore >= 75);
        const overrideNotes = (notesInput ? notesInput.value.trim() : '');
        const hasValidOverride = (!isAiHigh && overrideNotes.length >= 15);

        const canApprove = isComplete && allYes && (isAiHigh || hasValidOverride);

        if (approveButton) {
            approveButton.disabled = !canApprove;
            approveButton.setAttribute('aria-disabled', canApprove ? 'false' : 'true');

            if (isComplete && allYes && !isAiHigh) {
                approveButton.textContent = 'Setujui';
            } else {
                approveButton.textContent = 'Setujui Klaim';
            }
        }

        if (approveHint) {
            if (!isComplete) {
                approveHint.textContent = `Lengkapi ${missingCount} checklist wajib sebelum menyetujui klaim.`;
                approveHint.className = 'claim-validation-hint';
            } else if (hasAnyNo) {
                approveHint.textContent = 'Terdapat poin checklist bernilai "Tidak". Klaim memenuhi kriteria untuk DITOLAK (wajib isi alasan penolakan).';
                approveHint.className = 'claim-validation-hint is-warning text-danger';
            } else if (!isAiHigh && !hasValidOverride) {
                approveHint.textContent = `Skor AI rendah (${aiScore ?? 0}%). Tulis catatan verifikasi fisik minimal 15 karakter di atas untuk menyetujui klaim.`;
                approveHint.className = 'claim-validation-hint is-warning text-danger';
            } else {
                approveHint.textContent = isAiHigh
                    ? 'Checklist lengkap. Anda bisa menyetujui klaim.'
                    : 'Checklist lengkap dan justifikasi verifikasi fisik terpenuhi (Override Disetujui).';
                approveHint.className = 'claim-validation-hint is-ready';
            }
        }
    }

    // Backwards compatibility alias
    const updateApproveState = updateSubmitButtonState;

    // Reactive update on any radio or select change
    form.addEventListener('change', function (e) {
        if (e.target && (e.target.matches('input[type="radio"]') || e.target.matches('select'))) {
            updateSubmitButtonState();
        }
    });

    if (notesInput) {
        notesInput.addEventListener('input', updateSubmitButtonState);
    }

    // Quick Action: Autofill 3 physical checklist points dynamically based on AI Score
    if (autofillBtn) {
        autofillBtn.addEventListener('click', function (e) {
            e.preventDefault();

            const aiScore = getAiScore();

            if (aiScore !== null && !isNaN(aiScore) && aiScore >= 75) {
                // Skor >= 75: Isi 3 atribut fisik bernilai '1'
                physicalAiKeys.forEach(key => setChecklistValue(key, '1'));
            } else if (aiScore !== null && !isNaN(aiScore) && aiScore >= 50) {
                // Skor 50-74: Detail dan bukti visual bernilai 1, kecocokan data laporan bernilai 0
                setChecklistValue('detail_barang_valid', '1');
                setChecklistValue('bukti_visual_valid', '1');
                setChecklistValue('kecocokan_data_laporan', '0');
            } else {
                // Skor < 50: Bukti visual bergantung pada penalaran, detail dan kecocokan bernilai 0
                const visualMatches = isVisualReasoningSimilar();
                setChecklistValue('detail_barang_valid', '0');
                setChecklistValue('bukti_visual_valid', visualMatches ? '1' : '0');
                setChecklistValue('kecocokan_data_laporan', '0');
            }

            if (aiBanner) {
                if (aiBannerText) {
                    aiBannerText.innerHTML = (aiScore !== null && !isNaN(aiScore))
                        ? `Atribut fisik terisi via AI (<strong>${aiScore}%</strong>). <em>Mohon verifikasi identitas pelapor dan kronologi secara manual.</em>`
                        : `Atribut fisik terisi via AI. <em>Mohon verifikasi identitas pelapor dan kronologi secara manual.</em>`;
                }
                aiBanner.style.display = 'flex';
                aiBanner.classList.add('is-flash');
                setTimeout(() => aiBanner.classList.remove('is-flash'), 800);
            }

            updateSubmitButtonState();
        });
    }

    // Quick Action: Reset all checklist choices
    if (resetBtn) {
        resetBtn.addEventListener('click', function (e) {
            e.preventDefault();

            checklistKeys.forEach(key => {
                const radios = form.querySelectorAll(`input[name="${key}"]`);
                radios.forEach(r => { r.checked = false; });
                const select = form.querySelector(`select[name="${key}"]`);
                if (select) {
                    select.value = '';
                }
            });

            if (aiBanner) {
                aiBanner.style.display = 'none';
            }

            updateSubmitButtonState();
        });
    }

    // Rejection Validation: Ensure 'alasan_penolakan' is provided when rejecting
    function validateRejectionReason(e) {
        if (!reasonInput || !reasonInput.value.trim()) {
            if (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
            if (reasonInput) {
                reasonInput.focus();
                reasonInput.classList.add('is-invalid');
                let reasonError = form.querySelector('#alasan-penolakan-error');
                if (!reasonError) {
                    reasonError = document.createElement('small');
                    reasonError.id = 'alasan-penolakan-error';
                    reasonError.className = 'text-danger font-semibold mt-1 block';
                    reasonError.style.color = '#ef4444';
                    reasonError.style.display = 'block';
                    reasonError.style.fontSize = '12px';
                    reasonError.style.marginTop = '4px';
                    reasonInput.parentNode.appendChild(reasonError);
                }
                reasonError.textContent = 'Alasan penolakan wajib diisi jika Anda ingin menolak klaim.';
            }
            return false;
        }
        return true;
    }

    if (rejectBtn) {
        rejectBtn.addEventListener('click', function (e) {
            validateRejectionReason(e);
        }, true);
    }

    form.addEventListener('submit', function (e) {
        const submitter = e.submitter;
        const isReject = submitter && (
            submitter.classList.contains('danger') ||
            (submitter.getAttribute('formaction') && submitter.getAttribute('formaction').includes('reject'))
        );

        if (isReject && !validateRejectionReason(e)) {
            return false;
        }
    }, true);

    if (reasonInput) {
        reasonInput.addEventListener('input', function () {
            if (reasonInput.value.trim()) {
                reasonInput.classList.remove('is-invalid');
                const reasonError = form.querySelector('#alasan-penolakan-error');
                if (reasonError) reasonError.remove();
            }
        });
    }

    // Initialize state on page load
    updateSubmitButtonState();
}

export function bindClaimVerificationForm() {
    const form = document.querySelector('form[data-claim-verification-form]');
    if (!form) return;

    const approveButton = form.querySelector('[data-approve-btn]');
    const approveHint = form.querySelector('[data-approve-hint]');
    const autofillBtn = document.getElementById('btn-autofill-ai');
    const resetBtn = document.getElementById('btn-reset-checklist');
    const aiBanner = document.getElementById('checklist-ai-banner');
    const aiBannerText = document.getElementById('checklist-ai-banner-text');

    const checklistKeys = [
        'identitas_pelapor_valid',
        'detail_barang_valid',
        'kronologi_valid',
        'bukti_visual_valid',
        'kecocokan_data_laporan'
    ];

    const weights = {
        identitas_pelapor_valid: 20,
        detail_barang_valid: 25,
        kronologi_valid: 20,
        bukti_visual_valid: 20,
        kecocokan_data_laporan: 15
    };

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
     * Compute completion, score, and toggle approve button availability
     */
    function updateApproveState() {
        updatePillVisuals();

        let missingCount = 0;
        let score = 0;
        let allYes = true;

        checklistKeys.forEach(key => {
            const checkedRadio = form.querySelector(`input[name="${key}"]:checked`);
            if (checkedRadio) {
                if (checkedRadio.value === '1') {
                    score += weights[key] || 0;
                } else {
                    allYes = false;
                }
            } else {
                // Fallback for select element if present
                const select = form.querySelector(`select[name="${key}"]`);
                if (select && select.value !== '') {
                    if (select.value === '1') {
                        score += weights[key] || 0;
                    } else {
                        allYes = false;
                    }
                } else {
                    missingCount++;
                    allYes = false;
                }
            }
        });

        const isComplete = missingCount === 0;
        const canApprove = isComplete && score >= 75 && allYes;

        if (approveButton) {
            approveButton.disabled = !canApprove;
            approveButton.setAttribute('aria-disabled', canApprove ? 'false' : 'true');
        }

        if (approveHint) {
            if (!isComplete) {
                approveHint.textContent = `Lengkapi ${missingCount} checklist wajib sebelum menyetujui klaim.`;
                approveHint.className = 'claim-validation-hint';
            } else if (!allYes || score < 75) {
                approveHint.textContent = `Persetujuan memerlukan semua checklist bernilai "Ya" dan skor min. 75 (skor saat ini: ${score}).`;
                approveHint.className = 'claim-validation-hint is-warning';
            } else {
                approveHint.textContent = 'Checklist lengkap. Anda bisa menyetujui klaim.';
                approveHint.className = 'claim-validation-hint is-ready';
            }
        }
    }

    // Reactive update on any radio or select change
    form.addEventListener('change', function (e) {
        if (e.target && (e.target.matches('input[type="radio"]') || e.target.matches('select'))) {
            updateApproveState();
        }
    });

    // Quick Action: Autofill all checklist points via AI (set value 1)
    if (autofillBtn) {
        autofillBtn.addEventListener('click', function (e) {
            e.preventDefault();

            checklistKeys.forEach(key => {
                const yesRadio = form.querySelector(`input[name="${key}"][value="1"]`);
                if (yesRadio) {
                    yesRadio.checked = true;
                }
                const select = form.querySelector(`select[name="${key}"]`);
                if (select) {
                    select.value = '1';
                }
            });

            if (aiBanner) {
                const scoreAttr = autofillBtn.dataset.aiScore;
                if (aiBannerText) {
                    aiBannerText.innerHTML = scoreAttr
                        ? `Checklist terisi otomatis berdasarkan rekomendasi AI (<strong>${scoreAttr}%</strong>).`
                        : `Checklist terisi otomatis berdasarkan rekomendasi AI.`;
                }
                aiBanner.style.display = 'flex';
                aiBanner.classList.add('is-flash');
                setTimeout(() => aiBanner.classList.remove('is-flash'), 800);
            }

            updateApproveState();
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

            updateApproveState();
        });
    }

    // Initialize state on page load
    updateApproveState();
}

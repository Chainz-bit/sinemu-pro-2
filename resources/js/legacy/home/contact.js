export function initContactForm() {
    const contactForm = document.getElementById('contactForm');
    if (!contactForm) {
        return;
    }

    const nameInput = document.getElementById('contactName');
    const emailInput = document.getElementById('contactEmail');
    const phoneInput = document.getElementById('contactPhone');
    const messageInput = document.getElementById('contactMessage');
    const feedback = document.getElementById('contactFormFeedback');
    const supportWhatsappNumber = '6285174386642';

    function setFeedback(type, message) {
        if (!feedback) return;
        feedback.classList.remove('is-success', 'is-error');
        feedback.textContent = message;

        if (!message) {
            return;
        }

        feedback.classList.add(type === 'success' ? 'is-success' : 'is-error');
    }

    function validateForm() {
        const phonePattern = /^(\+62|0)[0-9\s-]{8,}$/;
        const fields = [nameInput, emailInput, phoneInput, messageInput];

        fields.forEach(function (field) {
            if (!field) return;
            field.classList.remove('is-invalid');
        });

        const name = (nameInput?.value || '').trim();
        const email = (emailInput?.value || '').trim();
        const phone = (phoneInput?.value || '').trim();
        const message = (messageInput?.value || '').trim();

        if (!name) {
            nameInput?.classList.add('is-invalid');
            setFeedback('error', 'Nama lengkap wajib diisi.');
            return false;
        }

        if (!email || !(emailInput && emailInput.checkValidity())) {
            emailInput?.classList.add('is-invalid');
            setFeedback('error', 'Alamat email tidak valid.');
            return false;
        }

        if (!phone || !phonePattern.test(phone)) {
            phoneInput?.classList.add('is-invalid');
            setFeedback('error', 'Nomor telepon tidak valid. Gunakan format 08... atau +62...');
            return false;
        }

        if (!message) {
            messageInput?.classList.add('is-invalid');
            setFeedback('error', 'Pesan wajib diisi.');
            return false;
        }

        return {
            name: name,
            email: email,
            phone: phone,
            message: message
        };
    }

    function buildWhatsappUrl(data) {
        const text = [
            'Halo Sinemu Support, saya ingin menghubungi tim support.',
            '',
            'Nama: ' + data.name,
            'Email: ' + data.email,
            'Telepon: ' + data.phone,
            '',
            'Pesan:',
            data.message
        ].join('\n');

        return 'https://wa.me/' + supportWhatsappNumber + '?text=' + encodeURIComponent(text);
    }

    contactForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const formData = validateForm();
        if (!formData) {
            return;
        }

        const whatsappUrl = buildWhatsappUrl(formData);
        const openedWindow = window.open(whatsappUrl, '_blank', 'noopener');

        if (!openedWindow) {
            window.location.href = whatsappUrl;
            return;
        }

        setFeedback('success', 'WhatsApp dibuka dengan pesan yang sudah terisi. Silakan tekan kirim di WhatsApp.');
        contactForm.reset();
        [nameInput, emailInput, phoneInput, messageInput].forEach(function (field) {
            if (!field) return;
            field.classList.remove('is-invalid');
        });
    });
}

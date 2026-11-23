(() => {
  const form = document.querySelector('[data-login-form]');
  if (!form) return;

  const submitButton = form.querySelector('[data-login-submit]');
  const submitLabel = submitButton?.querySelector('span');
  const password = form.querySelector('#password');
  const passwordToggle = form.querySelector('[data-password-toggle]');

  const validationMessage = field => {
    if (field.validity.valueMissing) {
      return field.type === 'email' ? 'Enter your email address.' : 'Enter your password.';
    }

    if (field.type === 'email' && field.validity.typeMismatch) {
      return 'Enter a valid email address.';
    }

    return '';
  };

  const updateError = field => {
    const error = document.getElementById(`login-${field.id}-error`);
    if (!error) return validationMessage(field);

    const message = validationMessage(field);
    error.textContent = message;
    error.hidden = !message;
    field.setAttribute('aria-invalid', String(Boolean(message)));

    if (message) {
      field.setAttribute('aria-describedby', error.id);
    } else {
      field.removeAttribute('aria-describedby');
    }

    return message;
  };

  form.querySelectorAll('input[required]').forEach(field => {
    field.addEventListener('input', () => updateError(field));
  });

  const firstInvalid = form.querySelector('input[aria-invalid="true"]');
  if (firstInvalid) firstInvalid.focus();

  passwordToggle?.addEventListener('click', () => {
    const reveal = password.type === 'password';
    password.type = reveal ? 'text' : 'password';
    passwordToggle.textContent = reveal ? 'Hide' : 'Show';
    passwordToggle.setAttribute('aria-label', `${reveal ? 'Hide' : 'Show'} password`);
    passwordToggle.setAttribute('aria-pressed', String(reveal));
  });

  form.addEventListener('submit', event => {
    const invalidField = [...form.querySelectorAll('input[required]')]
      .find(field => !field.validity.valid);

    if (invalidField) {
      event.preventDefault();
      updateError(invalidField);
      invalidField.focus();
      return;
    }

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.setAttribute('aria-busy', 'true');
    }
    if (submitLabel) submitLabel.textContent = 'Signing in…';
  });
})();

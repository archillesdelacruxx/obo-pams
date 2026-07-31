/* ==========================================================================
   PAMS — Form validation helpers (login + create user)
   ========================================================================== */
function setFieldError(inputEl, message){
  inputEl.classList.add('error');
  let err = inputEl.parentElement.querySelector('.error-msg');
  if (!err){
    err = document.createElement('div');
    err.className = 'error-msg';
    inputEl.parentElement.appendChild(err);
  }
  err.textContent = message;
}
function clearFieldError(inputEl){
  inputEl.classList.remove('error');
}

function validateLoginForm(usernameEl, passwordEl){
  let valid = true;
  clearFieldError(usernameEl); clearFieldError(passwordEl);
  if (!usernameEl.value.trim()){
    setFieldError(usernameEl, 'Username is required.'); valid = false;
  }
  if (!passwordEl.value.trim()){
    setFieldError(passwordEl, 'Password is required.'); valid = false;
  } else if (passwordEl.value.length < 4){
    setFieldError(passwordEl, 'Password must be at least 4 characters.'); valid = false;
  }
  return valid;
}

function validateCreateUserForm(form){
  let valid = true;
  const fullName = form.querySelector('[name="fullName"]');
  const username = form.querySelector('[name="username"]');
  const password = form.querySelector('[name="password"]');

  [fullName, username, password].forEach(clearFieldError);

  if (!fullName.value.trim()){ setFieldError(fullName, 'Full name is required.'); valid = false; }
  if (!username.value.trim()){ setFieldError(username, 'Username is required.'); valid = false; }
  else if (!/^[a-z0-9._]{4,}$/i.test(username.value.trim())){
    setFieldError(username, 'Use 4+ letters, numbers, dots or underscores.'); valid = false;
  }
  if (!password.value.trim()){ setFieldError(password, 'Temporary password is required.'); valid = false; }
  else if (password.value.length < 6){ setFieldError(password, 'Minimum 6 characters.'); valid = false; }

  return valid;
}

/* Toggle a password field's visibility, used by both login and create-user forms */
function initPasswordToggle(btnEl, inputEl){
  btnEl.addEventListener('click', () => {
    const isPw = inputEl.type === 'password';
    inputEl.type = isPw ? 'text' : 'password';
    btnEl.innerHTML = isPw
      ? '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a20.3 20.3 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>'
      : '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
  });
}

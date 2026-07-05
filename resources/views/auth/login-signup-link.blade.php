@php
    $registerRoute = 'filament.student.auth.register';
    $registerUrl = \Illuminate\Support\Facades\Route::has($registerRoute)
        ? route($registerRoute)
        : url('register');

    $currentLocale = app()->getLocale();
@endphp

<style>
    /* Scoped to login layout simple views */
    .fi-simple-header-subheading,
    .fi-simple-header .fi-simple-header-subheading,
    .fi-simple-header > p,
    .fi-simple-header .fi-link {
        display: none !important;
    }

    /* Outer Wrapper inside form card */
    .uhs-login-footer-wrapper {
        margin-top: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        width: 100%;
    }

    /* Custom Register Button - Styled as a elegant white button */
    .uhs-register-btn {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
        height: 46px;
        border-radius: 10px;
        border: 1px solid #d1d5db;
        background-color: #ffffff;
        color: #1f2937;
        font-weight: 700;
        font-size: 0.95rem;
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .uhs-register-btn:hover {
        background-color: #f9fafb;
        border-color: #9ca3af;
        transform: translateY(-0.5px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .uhs-register-btn:active {
        transform: translateY(0.5px);
    }

    /* Dark Mode Register Button */
    html.dark .uhs-register-btn {
        background-color: #27272a;
        border-color: #3f3f46;
        color: #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    }

    html.dark .uhs-register-btn:hover {
        background-color: #3f3f46;
        border-color: #52525b;
    }



    /* Language Switcher Section - Side by Side Buttons */
    .uhs-lang-selector {
        display: flex;
        gap: 0.875rem;
        width: 100%;
    }

    .uhs-lang-btn {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        height: 42px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        background-color: #ffffff;
        color: #4b5563;
        font-size: 0.9rem;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        font-family: 'Battambang', 'Kantumruy Pro', 'Inter', sans-serif !important;
    }

    .uhs-lang-btn:hover {
        background-color: #f9fafb;
        border-color: #d1d5db;
        color: #1f2937;
    }

    /* Active Language styling with blue accent */
    .uhs-lang-btn.active {
        border-color: #2563eb;
        color: #2563eb;
        background-color: rgba(37, 99, 235, 0.05);
        box-shadow: 0 0 0 1px #2563eb;
    }

    /* Dark Mode Language Switcher */
    html.dark .uhs-lang-btn {
        background-color: #18181b;
        border-color: #2f2f33;
        color: #a1a1aa;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }

    html.dark .uhs-lang-btn:hover {
        background-color: #27272a;
        border-color: #3f3f46;
        color: #ffffff;
    }

    html.dark .uhs-lang-btn.active {
        border-color: #3b82f6;
        color: #3b82f6;
        background-color: rgba(59, 130, 246, 0.1);
        box-shadow: 0 0 0 1px #3b82f6;
    }

    .uhs-lang-flag {
        width: 20px;
        height: 14px;
        border-radius: 2px;
        object-fit: cover;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    /* Forgot Password Link - Relocated above password field */
    .uhs-forgot-link {
        font-size: 0.875rem;
        font-weight: 700;
        color: #2563eb;
        text-decoration: none;
        transition: all 0.2s;
        font-family: 'Battambang', 'Kantumruy Pro', 'Inter', sans-serif !important;
    }

    .uhs-forgot-link:hover {
        text-decoration: underline;
        text-underline-offset: 4px;
        color: #1d4ed8;
    }

    html.dark .uhs-forgot-link {
        color: #60a5fa;
    }

    html.dark .uhs-forgot-link:hover {
        color: #93c5fd;
    }

    /* Custom label font styles */
    .uhs-custom-label-row label {
        font-family: 'Battambang', 'Kantumruy Pro', 'Inter', sans-serif !important;
    }

    /* Fix empty space above login button caused by remember me styling */
    .fi-simple-form .grid {
        gap: 1rem !important;
    }

    @media (max-width: 480px) {
        .uhs-lang-selector {
            gap: 0.5rem;
        }
    }
</style>

<div class="uhs-login-footer-wrapper">
    <!-- Language selector side by side buttons -->
    <div class="uhs-lang-selector">
        <a href="{{ route('language.set', ['locale' => 'km']) }}" class="uhs-lang-btn {{ $currentLocale === 'km' ? 'active' : '' }}">
            <img src="https://flagcdn.com/w40/kh.png" alt="Khmer flag" class="uhs-lang-flag">
            <span>ខ្មែរ</span>
        </a>
        <a href="{{ route('language.set', ['locale' => 'en']) }}" class="uhs-lang-btn {{ $currentLocale === 'en' ? 'active' : '' }}">
            <img src="https://flagcdn.com/w40/gb.png" alt="UK flag" class="uhs-lang-flag">
            <span>English</span>
        </a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const forgotPasswordUrl = "{{ route('student.password.request') }}";
        const forgotPasswordTextKm = "ភ្លេចពាក្យសម្ងាត់របស់អ្នក?";
        const forgotPasswordTextEn = "Forgot your password?";
        
        const registerUrl = "{{ $registerUrl }}";
        
        const injectForgotPasswordLink = () => {
            const passwordInput = document.getElementById('data.password') || document.querySelector('input[type="password"]');
            if (passwordInput) {
                // Hide Filament's default label to prevent duplicates
                const originalLabel = document.querySelector(`label[for="${passwordInput.id}"]`) || 
                                      (passwordInput.closest('.fi-fo-field-wrp') && passwordInput.closest('.fi-fo-field-wrp').querySelector('label'));
                if (originalLabel) {
                    originalLabel.style.setProperty('display', 'none', 'important');
                }
                
                // Get the input wrapper (the styled container)
                const inputWrapper = passwordInput.closest('.fi-input-wrp') || passwordInput;
                const parent = inputWrapper.parentElement;
                if (parent) {
                    let customRow = parent.querySelector('.uhs-custom-label-row');
                    if (!customRow) {
                        customRow = document.createElement('div');
                        customRow.className = 'uhs-custom-label-row';
                        customRow.style.display = 'flex';
                        customRow.style.justifyContent = 'space-between';
                        customRow.style.alignItems = 'center';
                        customRow.style.width = '100%';
                        customRow.style.marginBottom = '0.5rem';
                        
                        // 1. Create custom label for Left side
                        const customLabel = document.createElement('label');
                        customLabel.style.fontSize = '0.875rem';
                        customLabel.style.fontWeight = '700';
                        customLabel.style.color = 'var(--text-main, #1f2937)';
                        if (document.documentElement.classList.contains('dark')) {
                            customLabel.style.color = '#ffffff';
                        }
                        
                        const isKhmer = document.documentElement.lang === 'km' || document.querySelector('html').getAttribute('lang') === 'km';
                        if (isKhmer) {
                            customLabel.innerHTML = 'ពាក្យសម្ងាត់<span style="color: #f87171; margin-left: 2px;">*</span>';
                        } else {
                            customLabel.innerHTML = 'Password<span style="color: #f87171; margin-left: 2px;">*</span>';
                        }
                        customRow.appendChild(customLabel);
                        
                        // 2. Create forgot password link for Right side
                        const injectedLink = document.createElement('a');
                        injectedLink.href = forgotPasswordUrl;
                        injectedLink.className = 'uhs-forgot-link-injected uhs-forgot-link';
                        injectedLink.textContent = isKhmer ? forgotPasswordTextKm : forgotPasswordTextEn;
                        customRow.appendChild(injectedLink);
                        
                        // Insert the row above the password input wrapper
                        parent.insertBefore(customRow, inputWrapper);
                    } else {
                        // Keep text and styles updated on language switcher changes
                        const injectedLink = customRow.querySelector('.uhs-forgot-link-injected');
                        if (injectedLink) {
                            const isKhmer = document.documentElement.lang === 'km' || document.querySelector('html').getAttribute('lang') === 'km';
                            injectedLink.textContent = isKhmer ? forgotPasswordTextKm : forgotPasswordTextEn;
                        }
                    }
                }
            }
        };

        const injectRegisterLink = () => {
            const rememberInput = document.getElementById('data.remember') || document.querySelector('input[type="checkbox"]');
            if (rememberInput && registerUrl) {
                const label = rememberInput.closest('label');
                if (label) {
                    const labelParent = label.parentElement;
                    if (labelParent) {
                        // Style parent of the checkbox label as a flex row
                        labelParent.style.display = 'flex';
                        labelParent.style.justifyContent = 'space-between';
                        labelParent.style.alignItems = 'center';
                        labelParent.style.width = '100%';
                        labelParent.style.marginTop = '0.5rem';
                        labelParent.style.marginBottom = '0.5rem';
                        
                        // Check if already injected
                        let injectedRegister = labelParent.querySelector('.uhs-register-link-injected');
                        if (!injectedRegister) {
                            injectedRegister = document.createElement('a');
                            injectedRegister.href = registerUrl;
                            injectedRegister.className = 'uhs-register-link-injected uhs-forgot-link';
                            
                            const isKhmer = document.documentElement.lang === 'km' || document.querySelector('html').getAttribute('lang') === 'km';
                            injectedRegister.textContent = isKhmer ? 'បង្កើតគណនីថ្មី' : 'Create new account';
                            
                            labelParent.appendChild(injectedRegister);
                        } else {
                            // Keep text updated on language switcher changes
                            const isKhmer = document.documentElement.lang === 'km' || document.querySelector('html').getAttribute('lang') === 'km';
                            injectedRegister.textContent = isKhmer ? 'បង្កើតគណនីថ្មី' : 'Create new account';
                        }
                    }
                }
            }
        };

        const runInjections = () => {
            injectForgotPasswordLink();
            injectRegisterLink();
        };

        runInjections();

        // In case of dynamic DOM redraws by Livewire
        if (window.Livewire) {
            window.Livewire.on('navigated', runInjections);
            window.Livewire.on('element.updated', runInjections);
        }
        
        setInterval(runInjections, 500);
    });
</script>

<!DOCTYPE html>
<html lang="en" ng-app="stayAuth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login &middot; Stay Management System</title>

    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/login.css') ?>">

    <script>
        // Expose the application base URL to the AngularJS client.
        window.APP_BASE = "<?= base_url() ?>";
    </script>
</head>

<body class="auth-body">

<div class="auth-wrapper">
    <div class="auth-card">

        <div class="auth-visual"
             style="--hero-img: url('<?= base_url('assets/images/login_page_image.png') ?>');">

            <div class="auth-brand">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 21V8l9-5 9 5v13" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/>
                    <path d="M7 21v-6h10v6" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/>
                    <path d="M10 11h4" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <span>StayManager</span>
            </div>

            <div class="auth-quote">
                <p>&ldquo;Every stay, beautifully managed &mdash; from check-in to checkout.&rdquo;</p>
                <div class="auth-quote-name">Stay Management Suite</div>
                <div class="auth-quote-role">Hotel Operations Platform</div>
            </div>
        </div>

        <div class="auth-form-panel">
            <div class="auth-form" ng-controller="LoginController as vm">

                <div class="auth-brand-mobile">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 21V8l9-5 9 5v13" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M7 21v-6h10v6" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M10 11h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                    <span>StayManager</span>
                </div>

                <h1 class="auth-title">Hotel Management System</h1>
                <p class="auth-subtitle">
                    Sign in with your registered mobile number. We&rsquo;ll send you a
                    one-time password to continue.
                </p>

                <div class="auth-alert alert alert-danger mb-0" ng-if="vm.error" ng-cloak>
                    {{ vm.error }}
                </div>
                <div class="auth-alert alert alert-success mb-0" ng-if="vm.success" ng-cloak>
                    {{ vm.success }}
                </div>
                <div style="height:18px" ng-if="vm.error || vm.success"></div>

                <!-- Step 1: Request an OTP for the registered mobile number. -->
                <form ng-submit="vm.getOtp()" ng-show="vm.step === 1" novalidate>
                    <div class="form-floating mb-3">
                        <input type="tel"
                               class="form-control"
                               id="mobile_no"
                               name="mobile_no"
                               placeholder="Mobile Number"
                               autocomplete="tel"
                               maxlength="15"
                               ng-model="vm.mobile"
                               ng-class="{'is-invalid': vm.fieldError}"
                               ng-disabled="vm.loading"
                               ng-keypress="vm.onlyDigits($event)"
                               autofocus>
                        <label for="mobile_no">Mobile Number</label>
                    </div>

                    <button type="submit" class="btn-brand" ng-disabled="vm.loading">
                        <span ng-if="vm.loading" class="spinner-border btn-spinner" role="status" aria-hidden="true"></span>
                        <span>{{ vm.loading ? 'Sending OTP...' : 'Get OTP' }}</span>
                    </button>
                </form>

                <!-- Step 2: Verify the active OTP before it expires. -->
                <form ng-submit="vm.verifyOtp()" ng-show="vm.step === 2" novalidate>

                    <div class="auth-meta-row">
                        <span class="text-muted">
                            OTP sent to <strong>{{ vm.mobile }}</strong>
                        </span>
                        <button type="button" class="btn-link-brand" ng-click="vm.changeNumber()">
                            Change
                        </button>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text"
                               class="form-control otp-input"
                               id="otp"
                               name="otp"
                               placeholder="Enter OTP"
                               inputmode="numeric"
                               maxlength="6"
                               ng-model="vm.otp"
                               ng-class="{'is-invalid': vm.fieldError}"
                               ng-disabled="vm.loading"
                               ng-keypress="vm.onlyDigits($event)">
                        <label for="otp">Enter 6-digit OTP</label>
                    </div>

                    <button type="submit" class="btn-brand" ng-disabled="vm.loading">
                        <span ng-if="vm.loading" class="spinner-border btn-spinner" role="status" aria-hidden="true"></span>
                        <span>{{ vm.loading ? 'Verifying...' : 'Verify & Login' }}</span>
                    </button>

                    <div class="auth-meta-row mt-3 mb-0">
                        <span class="text-muted countdown" ng-if="vm.secondsLeft > 0">
                            Expires in {{ vm.timeLeft() }}
                        </span>
                        <span class="text-danger" ng-if="vm.secondsLeft === 0">OTP expired</span>
                        <button type="button" class="btn-link-brand"
                                ng-click="vm.getOtp()"
                                ng-disabled="vm.loading || vm.secondsLeft > 0">
                            Resend OTP
                        </button>
                    </div>

                    <!-- Compatibility fallback; remove when OTP delivery moves out of band. -->
                    <div class="dev-otp" ng-if="vm.devOtp" ng-cloak>
                        <div class="dev-otp-label">Your OTP</div>
                        <div class="dev-otp-code">{{ vm.devOtp }}</div>
                    </div>
                </form>

            </div>
        </div>

    </div>
</div>

<script src="<?= base_url('assets/js/angular.min.js') ?>"></script>
<script src="<?= base_url('assets/js/auth.js') ?>"></script>
</body>
</html>

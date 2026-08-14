/* ============================================================
   Stay Management — OTP Login (AngularJS 1.x)
   Handles the two-step flow: request OTP -> verify OTP.
   ============================================================ */
(function () {
    'use strict';

    angular.module('stayAuth', [])
        .controller('LoginController', ['$http', '$interval', LoginController]);

    function LoginController($http, $interval) {
        var vm = this;

        // Endpoint base (set on window by the view).
        var base = (window.APP_BASE || '/').replace(/\/?$/, '/');

        // ---- View state ----
        vm.step        = 1;      // 1 = mobile entry, 2 = OTP entry
        vm.mobile      = '';
        vm.otp         = '';
        vm.loading     = false;
        vm.error       = '';
        vm.success     = '';
        vm.fieldError  = false;
        vm.devOtp      = '';     // demo OTP echoed by the server
        vm.secondsLeft = 0;

        var timer = null;

        // ---- Public methods ----
        vm.getOtp       = getOtp;
        vm.verifyOtp    = verifyOtp;
        vm.changeNumber = changeNumber;
        vm.onlyDigits   = onlyDigits;
        vm.timeLeft     = timeLeft;

        // ---------------------------------------------------------------
        //  Step 1/Resend: request an OTP for the entered mobile number
        // ---------------------------------------------------------------
        function getOtp() {
            resetMessages();

            if (!/^[0-9]{10,15}$/.test(vm.mobile || '')) {
                vm.fieldError = true;
                vm.error = 'Please enter a valid mobile number (10–15 digits).';
                return;
            }

            vm.loading = true;

            $http.post(base + 'auth/send_otp', { mobile_no: vm.mobile })
                .then(function (res) {
                    var d = res.data || {};
                    if (d.status) {
                        vm.step    = 2;
                        vm.otp     = '';
                        vm.devOtp  = d.otp || '';
                        vm.success = d.message || 'OTP sent successfully.';
                        startCountdown(d.expires_in || 120);
                    } else {
                        vm.error = d.message || 'Unable to send OTP.';
                    }
                })
                .catch(networkError)
                .finally(function () { vm.loading = false; });
        }

        // ---------------------------------------------------------------
        //  Step 2: verify the OTP and log in
        // ---------------------------------------------------------------
        function verifyOtp() {
            resetMessages();

            if (!/^[0-9]{6}$/.test(vm.otp || '')) {
                vm.fieldError = true;
                vm.error = 'Please enter the 6-digit OTP.';
                return;
            }

            vm.loading = true;

            $http.post(base + 'auth/verify_otp', {
                mobile_no: vm.mobile,
                otp: vm.otp
            })
                .then(function (res) {
                    var d = res.data || {};
                    if (d.status) {
                        vm.success = d.message || 'Login successful.';
                        stopCountdown();
                        // Follow the application's configured landing page.
                        window.location.href = d.redirect || base;
                    } else {
                        vm.error = d.message || 'Verification failed.';
                        vm.fieldError = true;
                        // Server asks us to restart (expired / too many attempts).
                        if (d.reset) {
                            vm.otp = '';
                            stopCountdown();
                            vm.secondsLeft = 0;
                        }
                    }
                })
                .catch(networkError)
                .finally(function () { vm.loading = false; });
        }

        // ---------------------------------------------------------------
        //  Go back to the mobile-entry step
        // ---------------------------------------------------------------
        function changeNumber() {
            stopCountdown();
            resetMessages();
            vm.step        = 1;
            vm.otp         = '';
            vm.devOtp      = '';
            vm.secondsLeft = 0;
        }

        // ---- Countdown handling ----
        function startCountdown(seconds) {
            stopCountdown();
            vm.secondsLeft = seconds;
            timer = $interval(function () {
                vm.secondsLeft--;
                if (vm.secondsLeft <= 0) {
                    vm.secondsLeft = 0;
                    stopCountdown();
                }
            }, 1000);
        }

        function stopCountdown() {
            if (timer) {
                $interval.cancel(timer);
                timer = null;
            }
        }

        function timeLeft() {
            var m = Math.floor(vm.secondsLeft / 60);
            var s = vm.secondsLeft % 60;
            return m + ':' + (s < 10 ? '0' + s : s);
        }

        // ---- Helpers ----
        function onlyDigits(ev) {
            var ch = String.fromCharCode(ev.which || ev.keyCode);
            if (!/[0-9]/.test(ch)) {
                ev.preventDefault();
            }
        }

        function resetMessages() {
            vm.error      = '';
            vm.success    = '';
            vm.fieldError = false;
        }

        function networkError() {
            vm.error = 'Something went wrong. Please check your connection and try again.';
        }
    }
})();

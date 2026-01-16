/**
 * Modifies the Matomo Configuration variable in the Tag Manager UI to use the tracker domain URL
 */
(function () {
    // Wait for the Tag Manager UI to be fully loaded
    $(document).ready(function () {
        // If we're on the variable creation or edit page
        if (window.location.href.indexOf('TagManager/manageVariables') > -1) {
            // Resolve tracker domain from globals exposed in Matomo
            var trackerDomain = (window.piwik && piwik.trackerDomain) || window.matomoTrackerDomain;
            if (trackerDomain) {
				var trackerUrl = '//' + trackerDomain;
				if (window.console && console.debug) {
					console.debug('[TrackerDomain] UI helper loaded. trackerDomain =', trackerDomain);
				}
                // Function to set the URL in the form field
                function setTrackerDomainUrl() {
                    // Set a timeout to allow the form to be fully rendered
                    setTimeout(function () {
                        // Check if we're adding or editing a Matomo Configuration variable
                        var variableType = $('.variableType select').val();
                        if (variableType === 'MatomoConfiguration') {
                            // Find the matomoUrl field and set its value
							$('input[name="matomoUrl"]').val(trackerUrl).trigger('input').trigger('change');
							if (window.console && console.debug) {
								console.debug('[TrackerDomain] MatomoConfiguration.matomoUrl ->', trackerUrl);
							}
                            
                            // Also update any existing value in the field
                            var scope = angular.element('input[name="matomoUrl"]').scope();
                            if (scope && scope.formField) {
								scope.formField.value = trackerUrl;
                                scope.$apply();
                            }
                        }
                    }, 500);
                }
                
				// Keep trying briefly as Angular may re-render fields after async loads
				function reinforceUrlForShortPeriod() {
					var attempts = 0;
					var maxAttempts = 20; // ~6s at 300ms
					var intervalId = setInterval(function () {
						attempts++;
						setTrackerDomainUrl();
						if (attempts >= maxAttempts) {
							clearInterval(intervalId);
						}
					}, 300);
				}
				
                // Listen for variable type changes
				$(document).on('change', '.variableType select', function () {
					reinforceUrlForShortPeriod();
				});
                
                // Also run when the page loads
				reinforceUrlForShortPeriod();
            }
        }
    });
})(); 
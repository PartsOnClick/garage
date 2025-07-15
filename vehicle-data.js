/**
 * Vehicle Data AJAX Handler
 * Fixed version with proper escapeHtml function
 */

(function($) {
    'use strict';
    
    // Vehicle Data Manager
    window.FittingRequestVehicleData = {
        
        // Configuration
        config: {
            ajaxUrl: typeof fittingRequest !== 'undefined' ? fittingRequest.ajaxUrl : '/wp-admin/admin-ajax.php',
            nonce: typeof fittingRequest !== 'undefined' ? fittingRequest.nonce : '',
            debounceDelay: 300,
            minSearchLength: 2
        },
        
        // Cache for loaded data
        cache: {
            makes: null,
            models: {},
            searches: {}
        },
        
        // Initialize the vehicle data manager
        init: function() {
            // Check if we have the required configuration
            if (!this.config.nonce) {
                console.warn('Fitting Request: No nonce found. AJAX calls may fail.');
                return;
            }
            
            this.bindEvents();
            this.loadCarMakes();
        },
        
        // Bind events
        bindEvents: function() {
            var self = this;
            
            // Car make selection change
            $(document).on('change', '.fitting-car-make-select', function() {
                var $this = $(this);
                var make = $this.val();
                var $modelSelect = $this.closest('.fitting-vehicle-fields').find('.fitting-car-model-select');
                
                if (make) {
                    self.loadCarModels(make, $modelSelect);
                } else {
                    self.resetModelSelect($modelSelect);
                }
            });
            
            // Search functionality
            $(document).on('input', '.fitting-vehicle-search', this.debounce(function() {
                var $this = $(this);
                var searchTerm = $this.val().trim();
                var $results = $this.closest('.fitting-vehicle-search-container').find('.fitting-search-results');
                
                if (searchTerm.length >= self.config.minSearchLength) {
                    self.searchVehicles(searchTerm, $results);
                } else {
                    $results.empty().hide();
                }
            }, this.config.debounceDelay));
            
            // Search result selection
            $(document).on('click', '.fitting-search-result-item', function() {
                var $this = $(this);
                var make = $this.data('make');
                var model = $this.data('model');
                var yearFrom = $this.data('year-from');
                var yearTo = $this.data('year-to');
                
                self.selectVehicleFromSearch($this, make, model, yearFrom, yearTo);
            });
            
            // Clear search
            $(document).on('click', '.fitting-clear-search', function() {
                var $container = $(this).closest('.fitting-vehicle-search-container');
                $container.find('.fitting-vehicle-search').val('');
                $container.find('.fitting-search-results').empty().hide();
            });
        },
        
        // Load car makes
        loadCarMakes: function() {
            var self = this;
            
            // Return cached data if available
            if (this.cache.makes) {
                this.populateCarMakes(this.cache.makes);
                return;
            }
            
            // Show loading state
            $('.fitting-car-make-select').html('<option value="">' + this.getString('loading') + '</option>');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fitting_get_car_makes',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response && response.success && response.data && response.data.makes) {
                        self.cache.makes = response.data.makes;
                        self.populateCarMakes(response.data.makes);
                    } else {
                        self.handleMakeLoadError('Failed to load car makes: ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                    self.handleMakeLoadError('Network error while loading car makes: ' + error);
                }
            });
        },
        
        // Populate car makes dropdown
        populateCarMakes: function(makes) {
            var self = this;
            var options = '<option value="">' + this.getString('selectMake') + '</option>';
            
            if (makes && makes.length > 0) {
                $.each(makes, function(index, make) {
                    options += '<option value="' + self.escapeHtml(make) + '">' + self.escapeHtml(make) + '</option>';
                });
            } else {
                // Fallback to default makes
                var defaultMakes = ['Toyota', 'Honda', 'BMW', 'Mercedes-Benz', 'Audi', 'Nissan', 'Ford', 'Volkswagen'];
                $.each(defaultMakes, function(index, make) {
                    options += '<option value="' + self.escapeHtml(make) + '">' + self.escapeHtml(make) + '</option>';
                });
            }
            
            $('.fitting-car-make-select').html(options);
        },
        
        // Handle make loading error
        handleMakeLoadError: function(message) {
            console.error('Fitting Request:', message);
            
            // Show default makes
            var defaultMakes = ['Toyota', 'Honda', 'BMW', 'Mercedes-Benz', 'Audi', 'Nissan', 'Ford', 'Volkswagen'];
            var options = '<option value="">' + this.getString('selectMake') + '</option>';
            var self = this;
            
            $.each(defaultMakes, function(index, make) {
                options += '<option value="' + self.escapeHtml(make) + '">' + self.escapeHtml(make) + '</option>';
            });
            
            $('.fitting-car-make-select').html(options);
        },
        
        // Load car models for a specific make
        loadCarModels: function(make, $modelSelect) {
            var self = this;
            
            // Show loading state
            $modelSelect.html('<option value="">' + this.getString('loading') + '</option>').prop('disabled', true);
            
            // Check cache first
            if (this.cache.models[make]) {
                this.populateCarModels(this.cache.models[make], $modelSelect);
                return;
            }
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fitting_get_car_models',
                    make: make,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response && response.success && response.data && response.data.models) {
                        self.cache.models[make] = response.data.models;
                        self.populateCarModels(response.data.models, $modelSelect);
                    } else {
                        self.handleModelLoadError($modelSelect, response.data ? response.data.message : 'Unknown error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                    self.handleModelLoadError($modelSelect, 'Network error while loading models: ' + error);
                }
            });
        },
        
        // Populate car models dropdown
        populateCarModels: function(models, $modelSelect) {
            var self = this;
            var options = '<option value="">' + this.getString('selectModel') + '</option>';
            
            if (models && models.length > 0) {
                $.each(models, function(index, modelData) {
                    var model = typeof modelData === 'string' ? modelData : modelData.model;
                    var yearRange = '';
                    
                    if (typeof modelData === 'object' && modelData.year_from) {
                        yearRange = ' (' + modelData.year_from + '-' + (modelData.year_to || 'Present') + ')';
                    }
                    
                    options += '<option value="' + self.escapeHtml(model) + '" data-year-from="' + 
                              (modelData.year_from || '') + '" data-year-to="' + (modelData.year_to || '') + '">' + 
                              self.escapeHtml(model) + yearRange + '</option>';
                });
            } else {
                // Fallback models
                var fallbackModels = ['Sedan', 'SUV', 'Hatchback', 'Coupe', 'Convertible'];
                $.each(fallbackModels, function(index, model) {
                    options += '<option value="' + self.escapeHtml(model) + '">' + self.escapeHtml(model) + '</option>';
                });
            }
            
            $modelSelect.html(options).prop('disabled', false);
        },
        
        // Handle model loading error
        handleModelLoadError: function($modelSelect, message) {
            console.error('Fitting Request:', message);
            var self = this;
            
            var fallbackModels = ['Sedan', 'SUV', 'Hatchback', 'Coupe', 'Convertible'];
            var options = '<option value="">' + this.getString('selectModel') + '</option>';
            
            $.each(fallbackModels, function(index, model) {
                options += '<option value="' + self.escapeHtml(model) + '">' + self.escapeHtml(model) + '</option>';
            });
            
            $modelSelect.html(options).prop('disabled', false);
        },
        
        // Reset model select
        resetModelSelect: function($modelSelect) {
            $modelSelect.html('<option value="">' + this.getString('selectModel') + '</option>')
                       .prop('disabled', false);
        },
        
        // Search vehicles
        searchVehicles: function(searchTerm, $results) {
            var self = this;
            
            // Check cache first
            if (this.cache.searches[searchTerm]) {
                this.displaySearchResults(this.cache.searches[searchTerm], $results);
                return;
            }
            
            // Show loading
            $results.html('<div class="fitting-search-loading">' + this.getString('searching') + '</div>').show();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fitting_search_vehicles',
                    search: searchTerm,
                    limit: 20,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response && response.success && response.data && response.data.results) {
                        self.cache.searches[searchTerm] = response.data.results;
                        self.displaySearchResults(response.data.results, $results);
                    } else {
                        $results.html('<div class="fitting-search-error">' + 
                                    (response.data ? response.data.message : 'Search failed') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Search AJAX Error:', {xhr: xhr, status: status, error: error});
                    $results.html('<div class="fitting-search-error">Network error during search</div>');
                }
            });
        },
        
        // Display search results
        displaySearchResults: function(results, $results) {
            var self = this;
            var html = '';
            
            if (!results || results.length === 0) {
                html = '<div class="fitting-search-no-results">' + this.getString('noResults') + '</div>';
            } else {
                html = '<div class="fitting-search-results-list">';
                
                $.each(results, function(index, vehicle) {
                    html += '<div class="fitting-search-result-item" ' +
                           'data-make="' + self.escapeHtml(vehicle.make) + '" ' +
                           'data-model="' + self.escapeHtml(vehicle.model) + '" ' +
                           'data-year-from="' + (vehicle.year_from || '') + '" ' +
                           'data-year-to="' + (vehicle.year_to || '') + '">' +
                           '<div class="fitting-vehicle-name">' + self.escapeHtml(vehicle.display_name) + '</div>' +
                           '</div>';
                });
                
                html += '</div>';
            }
            
            $results.html(html).show();
        },
        
        // Select vehicle from search results
        selectVehicleFromSearch: function($item, make, model, yearFrom, yearTo) {
            var $container = $item.closest('.fitting-vehicle-search-container');
            var $form = $container.closest('.fitting-request-form, .fitting-vehicle-form');
            
            // Update form fields
            $form.find('.fitting-car-make-select').val(make).trigger('change');
            
            // Wait for models to load, then select the model
            var self = this;
            setTimeout(function() {
                $form.find('.fitting-car-model-select').val(model);
                
                // Update year fields if they exist
                if (yearFrom) {
                    $form.find('.fitting-car-year-from').val(yearFrom);
                }
                if (yearTo) {
                    $form.find('.fitting-car-year-to').val(yearTo);
                }
            }, 500);
            
            // Clear search
            $container.find('.fitting-vehicle-search').val('');
            $container.find('.fitting-search-results').empty().hide();
            
            // Show success notification
            this.showNotification(this.getString('vehicleSelected'), 'success');
        },
        
        // Get localized string
        getString: function(key) {
            if (typeof fittingRequest !== 'undefined' && fittingRequest.strings && fittingRequest.strings[key]) {
                return fittingRequest.strings[key];
            }
            
            // Fallback strings
            var fallbacks = {
                'loading': 'Loading...',
                'selectMake': 'Select Car Make',
                'selectModel': 'Select Car Model',
                'searching': 'Searching...',
                'noResults': 'No vehicles found',
                'vehicleSelected': 'Vehicle selected',
                'makeRequired': 'Please select a car make',
                'modelRequired': 'Please select a car model',
                'errorLoadingModels': 'Error loading models'
            };
            
            return fallbacks[key] || key;
        },
        
        // Validate vehicle selection
        validateVehicleSelection: function($form) {
            var errors = [];
            var make = $form.find('.fitting-car-make-select').val();
            var model = $form.find('.fitting-car-model-select').val();
            
            if (!make) {
                errors.push(this.getString('makeRequired'));
            }
            
            if (!model) {
                errors.push(this.getString('modelRequired'));
            }
            
            return {
                isValid: errors.length === 0,
                errors: errors
            };
        },
        
        // Get selected vehicle data
        getSelectedVehicle: function($form) {
            var $makeSelect = $form.find('.fitting-car-make-select');
            var $modelSelect = $form.find('.fitting-car-model-select');
            var $modelOption = $modelSelect.find('option:selected');
            
            return {
                make: $makeSelect.val(),
                model: $modelSelect.val(),
                year_from: $modelOption.data('year-from') || '',
                year_to: $modelOption.data('year-to') || ''
            };
        },
        
        // Show notification
        showNotification: function(message, type) {
            type = type || 'info';
            
            var $notification = $('<div class="fitting-notification fitting-notification-' + type + '">' +
                                '<span class="fitting-notification-message">' + this.escapeHtml(message) + '</span>' +
                                '<button class="fitting-notification-close">&times;</button>' +
                                '</div>');
            
            $('body').append($notification);
            
            $notification.fadeIn();
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $notification.remove();
                });
            }, 5000);
            
            // Manual close
            $notification.find('.fitting-notification-close').on('click', function() {
                $notification.fadeOut(function() {
                    $notification.remove();
                });
            });
        },
        
        // Handle errors
        handleError: function(message) {
            console.error('Fitting Request Vehicle Data Error:', message);
            this.showNotification(message, 'error');
        },
        
        // Debounce function
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },
        
        // Escape HTML - THIS WAS THE MISSING FUNCTION
        escapeHtml: function(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        },
        
        // Clear all cache
        clearCache: function() {
            this.cache = {
                makes: null,
                models: {},
                searches: {}
            };
        },
        
        // Refresh data
        refresh: function() {
            this.clearCache();
            this.loadCarMakes();
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Wait a bit for other scripts to load
        setTimeout(function() {
            FittingRequestVehicleData.init();
        }, 100);
    });
    
    // Also expose to global scope for external access
    window.FittingRequestVehicleData = FittingRequestVehicleData;
    
})(jQuery);

// Global test function for debugging
window.testFittingVehicleData = function() {
    console.log('Testing Fitting Request Vehicle Data...');
    console.log('Config:', FittingRequestVehicleData.config);
    
    // Test AJAX endpoint
    jQuery.ajax({
        url: FittingRequestVehicleData.config.ajaxUrl,
        type: 'POST',
        data: {
            action: 'fitting_get_car_makes',
            nonce: FittingRequestVehicleData.config.nonce
        },
        success: function(response) {
            console.log('? AJAX Test Success:', response);
        },
        error: function(xhr, status, error) {
            console.error('? AJAX Test Failed:', {xhr: xhr, status: status, error: error});
        }
    });
};
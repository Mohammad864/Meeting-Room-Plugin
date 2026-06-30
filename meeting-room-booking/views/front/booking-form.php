<?php
/**
 * Template: Front / Booking Form
 *
 * Variables (extracted by View::render):
 *   @var array|null $statusMessage  null, or ['type' => string, 'html' => string]
 *   @var string     $actionUrl      URL for the form action attribute
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<?php if ($statusMessage !== null) : ?>
    <?php echo wp_kses_post($statusMessage['html']); ?>
<?php endif; ?>

<form method="post"
      action="<?php echo esc_url($actionUrl); ?>"
      class="mrb-booking-form"
      id="mrb-booking-form"
      novalidate>

    <?php wp_nonce_field('mrb_submit_booking', 'mrb_nonce'); ?>
    <input type="hidden" name="action" value="mrb_submit_booking">

    <input type="hidden" name="start_time" id="mrb_start_time">
    <input type="hidden" name="end_time" id="mrb_end_time">

    <div class="mrb-card mrb-booking-card">

        <div class="mrb-header mrb-booking-header">
            <div>
                <span class="mrb-eyebrow">Meeting room booking</span>
                <h2 class="mrb-title">Reserve a meeting room</h2>
                <p class="mrb-subtitle">
                    Choose your meeting details, pick an available time, and submit your reservation.
                </p>
            </div>
        </div>

        <div class="mrb-steps" aria-label="Booking steps">

            <button type="button" class="mrb-step is-active" data-target="meeting">
                <span>1</span>
                <strong>Meeting</strong>
            </button>

            <button type="button" class="mrb-step" data-target="time">
                <span>2</span>
                <strong>Time</strong>
            </button>

            <button type="button" class="mrb-step" data-target="contact">
                <span>3</span>
                <strong>Contact</strong>
            </button>

        </div>


        <div id="mrb-form-feedback" class="mrb-form-feedback" style="display:none;"></div>

        <section id="mrb-section-meeting" class="mrb-section">
            <div class="mrb-section-header">
                <h3>Meeting details</h3>
                <p>Tell us what the reservation is for.</p>
            </div>

            <div class="mrb-field">
                <label for="mrb_meeting_title">Meeting Title <span aria-hidden="true">*</span></label>
                <input
                    type="text"
                    name="meeting_title"
                    id="mrb_meeting_title"
                    placeholder="Example: Weekly planning meeting"
                    autocomplete="off"
                    required>
            </div>

            <div class="mrb-field">
                <label for="mrb_description">Description</label>
                <textarea
                    name="description"
                    id="mrb_description"
                    rows="4"
                    placeholder="Optional notes, agenda, equipment needs, or extra details."></textarea>
            </div>
        </section>

        <section id="mrb-section-time" class="mrb-section">
            <div class="mrb-section-header">
                <h3>Date and time</h3>
                <p>Select a date, then choose an available time from the calendar.</p>
            </div>

            <div class="mrb-grid mrb-grid-date">
                <div class="mrb-field">
                    <label for="mrb_meeting_date">Meeting Date <span aria-hidden="true">*</span></label>
                    <input
                        type="date"
                        name="meeting_date"
                        id="mrb_meeting_date"
                        required>
                </div>

                <div class="mrb-field mrb-selected-time-field">
                    <label>Selected Time <span aria-hidden="true">*</span></label>

                    <div class="mrb-selected-time-box" id="mrb-selected-time-box">
                        <div class="mrb-selected-time-icon">&#9203;</div>
                        <div>
                            <strong id="mrb-selected-time-text">No time selected yet</strong>
                            <span id="mrb-selected-time-help">Use the availability calendar to choose a free slot.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mrb-time-actions">
                <button type="button" id="mrb-open-calendar" class="mrb-open-calendar-btn">
                    <span aria-hidden="true">&#128197;</span>
                    Choose available time
                </button>

                <button type="button" id="mrb-clear-time" class="mrb-clear-time-btn" style="display:none;">
                    Clear selected time
                </button>
            </div>

            <div id="mrb-time-conflict-warning" class="mrb-time-conflict-warning" style="display:none;">
                The selected time overlaps with an existing reservation. Please choose an empty time.
            </div>
        </section>

        <section id="mrb-section-contact" class="mrb-section">
            <div class="mrb-section-header">
                <h3>Contact information</h3>
                <p>We will use this information to confirm your reservation.</p>
            </div>

            <div class="mrb-grid">
                <div class="mrb-field">
                    <label for="mrb_first_name">First Name <span aria-hidden="true">*</span></label>
                    <input
                        type="text"
                        name="first_name"
                        id="mrb_first_name"
                        placeholder="First name"
                        autocomplete="given-name"
                        required>
                </div>

                <div class="mrb-field">
                    <label for="mrb_last_name">Last Name <span aria-hidden="true">*</span></label>
                    <input
                        type="text"
                        name="last_name"
                        id="mrb_last_name"
                        placeholder="Last name"
                        autocomplete="family-name"
                        required>
                </div>

                <div class="mrb-field">
                    <label for="mrb_email">Email <span aria-hidden="true">*</span></label>
                    <input
                        type="email"
                        name="email"
                        id="mrb_email"
                        placeholder="name@example.com"
                        autocomplete="email"
                        required>
                </div>

                <div class="mrb-field">
                    <label for="mrb_mobile">Mobile <span aria-hidden="true">*</span></label>
                    <input
                        type="tel"
                        name="mobile"
                        id="mrb_mobile"
                        placeholder="Your mobile number"
                        autocomplete="tel"
                        required>
                </div>
            </div>
        </section>

        <div class="mrb-submit-area">
            <button type="submit" class="mrb-submit-btn" id="mrb-submit-btn">
                <span class="mrb-submit-label">Reserve Room</span>
                <span class="mrb-submit-loading" style="display:none;">Submitting...</span>
            </button>

            <p class="mrb-submit-note">
                Your reservation will be checked against existing bookings before it is confirmed.
            </p>
        </div>

    </div>

    <div id="mrb-calendar-modal"
         class="mrb-calendar-modal"
         aria-hidden="true">

        <div class="mrb-calendar-modal-backdrop"></div>

        <div class="mrb-calendar-modal-panel"
             role="dialog"
             aria-modal="true"
             aria-labelledby="mrb-calendar-modal-title">

            <div class="mrb-calendar-modal-header">
                <div>
                    <h3 class="mrb-calendar-modal-title" id="mrb-calendar-modal-title">
                        Choose reservation time
                    </h3>
                    <p class="mrb-calendar-modal-subtitle">
                        Drag on a free area to select your meeting time. Time precision is 30 minutes.
                    </p>
                </div>

                <button type="button"
                        id="mrb-calendar-close"
                        class="mrb-calendar-close-btn"
                        aria-label="Close calendar">
                    &times;
                </button>
            </div>

            <div id="mrb-calendar-error-box" class="mrb-calendar-error" style="display:none;"></div>

            <div class="mrb-calendar-layout">

                <aside class="mrb-calendar-days-panel">
                    <div class="mrb-calendar-days-heading">Available days</div>
                    <div id="mrb-calendar-days" class="mrb-calendar-days">
                        Loading days...
                    </div>
                </aside>

                <div class="mrb-calendar-timeline-panel">

                    <div class="mrb-calendar-current-day">
                        <div>
                            <strong id="mrb-current-day-title">Select a date</strong>
                            <span id="mrb-current-day-subtitle"></span>
                        </div>

                        <div id="mrb-current-selection-label" class="mrb-current-selection-label">
                            Drag to select
                        </div>
                    </div>

                    <div id="mrb-calendar-loading" class="mrb-calendar-loading" style="display:none;">
                        Loading availability...
                    </div>

                    <div id="mrb-google-timeline-scroll" class="mrb-google-timeline-scroll">

                        <div id="mrb-google-timeline" class="mrb-google-timeline">

                            <div id="mrb-timeline-labels" class="mrb-timeline-labels"></div>

                            <div id="mrb-timeline-grid" class="mrb-timeline-grid">
                                <div id="mrb-booked-layer" class="mrb-booked-layer"></div>
                                <div id="mrb-selection-layer" class="mrb-selection-layer"></div>
                            </div>

                        </div>

                    </div>

                    <div class="mrb-calendar-help">
                        <span class="mrb-help-item"><span class="mrb-dot mrb-dot-booked"></span> Booked</span>
                        <span class="mrb-help-item"><span class="mrb-dot mrb-dot-selected"></span> Your selection</span>
                        <span class="mrb-help-item"><span class="mrb-dot mrb-dot-free"></span> Free</span>
                    </div>

                </div>

            </div>

        </div>

    </div>

</form>

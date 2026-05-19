    <!-- DONATE MODAL (per rescued pet) -->
    <div id="donateModal" class="modal-backdrop" onclick="closeDonateModal(event)">
        <div class="modal-card" style="max-width: 520px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="donateModalTitle">Donate for this pet</h3>
                <button type="button" class="btn-close-modal" onclick="forceCloseDonateModal()">✕</button>
            </div>
            <div style="display: flex; gap: 16px; padding: 0 24px 16px; align-items: center;">
                <img id="donateModalImage" src="" alt="" style="width: 88px; height: 88px; border-radius: 12px; object-fit: cover; border: 1px solid rgba(255,255,255,0.1);">
                <div>
                    <p id="donateModalPetName" style="margin: 0; font-weight: 800; color: #fff;"></p>
                    <p id="donateModalPetBreed" style="margin: 4px 0 0; opacity: 0.65; font-size: 0.9rem;"></p>
                </div>
            </div>
            <form class="report-form" action="submit_donation.php" method="POST" style="padding-top: 0;">
                <input type="hidden" name="pet_id" id="donate_modal_pet_id">
                <input type="hidden" name="pet_name" id="donate_modal_pet_name">
                <label>Donation Type</label>
                <select name="donation_type" id="donate_modal_type" onchange="toggleDonateModalAmount()" required>
                    <option value="">Select</option>
                    <option value="dog_food">Dog Food</option>
                    <option value="cat_food">Cat Food</option>
                    <option value="vitamins">Vitamins</option>
                    <option value="supplies">Pet Supplies</option>
                    <option value="money">Money</option>
                </select>
                <div id="donateModalAmountBox" style="display: none;">
                    <label>Amount (₱)</label>
                    <input type="number" name="amount" id="donate_modal_amount" min="1" step="1">
                </div>
                <label>Message (optional)</label>
                <textarea name="message" rows="3" placeholder="e.g. For food and medicine…"></textarea>
                <button type="submit" class="btn-gold" style="width: 100%; margin-top: 8px;">
                    <i class="fas fa-hand-holding-heart"></i> Continue to receipt
                </button>
            </form>
        </div>
    </div>

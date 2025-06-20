<template>
  <div class="gcu-voting-dashboard">
    <div class="dashboard-header">
      <h1>{{ basketName }} Voting Dashboard</h1>
      <p class="subtitle">Participate in democratic governance of the {{ basketCode }}</p>
    </div>

    <!-- Stats Overview -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="stat-content">
          <h3>Active Polls</h3>
          <p class="stat-value">{{ stats.active_polls }}</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
          </svg>
        </div>
        <div class="stat-content">
          <h3>Votes Cast</h3>
          <p class="stat-value">{{ stats.votes_cast }}</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">
          <span class="currency-symbol">{{ basketSymbol }}</span>
        </div>
        <div class="stat-content">
          <h3>{{ basketCode }} Balance</h3>
          <p class="stat-value">{{ formatBalance(stats.gcu_balance) }}</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
          </svg>
        </div>
        <div class="stat-content">
          <h3>Voting Power</h3>
          <p class="stat-value">{{ formatNumber(stats.voting_power) }}</p>
        </div>
      </div>
    </div>

    <!-- Active Polls -->
    <div class="polls-section" v-if="activePolls.length > 0">
      <h2>Active Polls</h2>
      <div class="polls-grid">
        <div v-for="poll in activePolls" :key="poll.uuid" class="poll-card">
          <div class="poll-header">
            <h3>{{ poll.title }}</h3>
            <span class="poll-badge" :class="poll.user_context.has_voted ? 'voted' : 'not-voted'">
              {{ poll.user_context.has_voted ? 'Voted' : 'Not Voted' }}
            </span>
          </div>
          
          <p class="poll-description">{{ poll.description }}</p>
          
          <div class="poll-meta">
            <div class="meta-item">
              <span class="meta-label">Ends in:</span>
              <span class="meta-value">{{ poll.time_remaining.human_readable }}</span>
            </div>
            <div class="meta-item">
              <span class="meta-label">Participation:</span>
              <span class="meta-value">{{ poll.current_participation }}%</span>
            </div>
          </div>

          <button 
            v-if="!poll.user_context.has_voted && poll.user_context.can_vote"
            @click="openVotingModal(poll)"
            class="vote-button"
          >
            Cast Your Vote
          </button>
          
          <div v-else-if="poll.user_context.has_voted" class="vote-info">
            <p>You voted on {{ formatDate(poll.user_context.vote.voted_at) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Upcoming Polls -->
    <div class="polls-section" v-if="upcomingPolls.length > 0">
      <h2>Upcoming Polls</h2>
      <div class="upcoming-polls">
        <div v-for="poll in upcomingPolls" :key="poll.uuid" class="upcoming-poll">
          <h4>{{ poll.title }}</h4>
          <p>Starts {{ formatDate(poll.start_date) }}</p>
        </div>
      </div>
    </div>

    <!-- Voting Modal -->
    <div v-if="showVotingModal" class="modal-overlay" @click="closeVotingModal">
      <div class="modal-content" @click.stop>
        <h2>{{ currentPoll.title }}</h2>
        <p>Allocate percentages to each currency in the basket. Total must equal 100%.</p>
        
        <div class="allocation-form">
          <div v-for="currency in currentPoll.options[0].currencies" :key="currency.code" class="allocation-item">
            <label>
              <span class="currency-name">{{ currency.name }} ({{ currency.code }})</span>
              <input 
                type="number" 
                v-model.number="allocations[currency.code]"
                :min="currency.min"
                :max="currency.max"
                step="0.1"
                @input="updateTotal"
              />
              <span class="percentage">%</span>
            </label>
            <span class="range-info">{{ currency.min }}% - {{ currency.max }}%</span>
          </div>
          
          <div class="total-row" :class="{ 'invalid': totalAllocation !== 100 }">
            <span>Total:</span>
            <span>{{ totalAllocation }}%</span>
          </div>
        </div>

        <div class="modal-actions">
          <button @click="closeVotingModal" class="cancel-button">Cancel</button>
          <button 
            @click="submitVote" 
            :disabled="totalAllocation !== 100 || submitting"
            class="submit-button"
          >
            {{ submitting ? 'Submitting...' : 'Submit Vote' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

export default {
  name: 'GCUVotingDashboard',
  setup() {
    // State
    const stats = ref({
      active_polls: 0,
      votes_cast: 0,
      gcu_balance: 0,
      voting_power: 0
    });
    
    const activePolls = ref([]);
    const upcomingPolls = ref([]);
    const basketInfo = ref({
      name: 'Global Currency Unit',
      code: 'GCU',
      symbol: 'Ǥ'
    });
    
    const showVotingModal = ref(false);
    const currentPoll = ref(null);
    const allocations = ref({});
    const submitting = ref(false);
    
    // Computed
    const totalAllocation = computed(() => {
      return Object.values(allocations.value).reduce((sum, val) => sum + (val || 0), 0);
    });
    
    const basketName = computed(() => basketInfo.value.name);
    const basketCode = computed(() => basketInfo.value.code);
    const basketSymbol = computed(() => basketInfo.value.symbol);
    
    // Methods
    const loadDashboard = async () => {
      try {
        const response = await axios.get('/api/voting/dashboard');
        stats.value = response.data.data.stats;
        basketInfo.value = response.data.data.basket_info;
      } catch (error) {
        console.error('Error loading dashboard:', error);
      }
    };
    
    const loadActivePolls = async () => {
      try {
        const response = await axios.get('/api/voting/polls');
        activePolls.value = response.data.data;
        basketInfo.value = response.data.meta;
      } catch (error) {
        console.error('Error loading active polls:', error);
      }
    };
    
    const loadUpcomingPolls = async () => {
      try {
        const response = await axios.get('/api/voting/polls/upcoming');
        upcomingPolls.value = response.data.data;
      } catch (error) {
        console.error('Error loading upcoming polls:', error);
      }
    };
    
    const openVotingModal = (poll) => {
      currentPoll.value = poll;
      showVotingModal.value = true;
      
      // Initialize allocations with default values
      allocations.value = {};
      poll.options[0].currencies.forEach(currency => {
        allocations.value[currency.code] = currency.default;
      });
    };
    
    const closeVotingModal = () => {
      showVotingModal.value = false;
      currentPoll.value = null;
      allocations.value = {};
    };
    
    const updateTotal = () => {
      // Force reactivity update
      allocations.value = { ...allocations.value };
    };
    
    const submitVote = async () => {
      if (totalAllocation.value !== 100 || submitting.value) return;
      
      submitting.value = true;
      
      try {
        await axios.post(`/api/voting/polls/${currentPoll.value.uuid}/vote`, {
          allocations: allocations.value
        });
        
        // Refresh data
        await Promise.all([
          loadDashboard(),
          loadActivePolls()
        ]);
        
        closeVotingModal();
        
        // Show success message (implement toast/notification)
        alert('Your vote has been successfully recorded!');
      } catch (error) {
        console.error('Error submitting vote:', error);
        alert(error.response?.data?.error || 'Failed to submit vote');
      } finally {
        submitting.value = false;
      }
    };
    
    const formatBalance = (value) => {
      return (value / 100).toFixed(2);
    };
    
    const formatNumber = (value) => {
      return new Intl.NumberFormat().format(value);
    };
    
    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString();
    };
    
    // Lifecycle
    onMounted(() => {
      Promise.all([
        loadDashboard(),
        loadActivePolls(),
        loadUpcomingPolls()
      ]);
    });
    
    return {
      stats,
      activePolls,
      upcomingPolls,
      basketName,
      basketCode,
      basketSymbol,
      showVotingModal,
      currentPoll,
      allocations,
      totalAllocation,
      submitting,
      openVotingModal,
      closeVotingModal,
      updateTotal,
      submitVote,
      formatBalance,
      formatNumber,
      formatDate
    };
  }
};
</script>

<style scoped>
.gcu-voting-dashboard {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.dashboard-header {
  margin-bottom: 2rem;
}

.dashboard-header h1 {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.subtitle {
  color: #6b7280;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 3rem;
}

.stat-card {
  background: white;
  border-radius: 0.5rem;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 1rem;
}

.stat-icon {
  width: 3rem;
  height: 3rem;
  color: #6366f1;
}

.currency-symbol {
  font-size: 2rem;
  font-weight: bold;
  color: #6366f1;
}

.stat-content h3 {
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: bold;
  color: #111827;
}

.polls-section {
  margin-bottom: 3rem;
}

.polls-section h2 {
  margin-bottom: 1.5rem;
}

.polls-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.poll-card {
  background: white;
  border-radius: 0.5rem;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.poll-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 1rem;
}

.poll-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 500;
}

.poll-badge.voted {
  background: #d1fae5;
  color: #065f46;
}

.poll-badge.not-voted {
  background: #fee2e2;
  color: #991b1b;
}

.poll-description {
  color: #4b5563;
  margin-bottom: 1rem;
  line-height: 1.5;
}

.poll-meta {
  display: flex;
  gap: 2rem;
  margin-bottom: 1rem;
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.meta-label {
  font-size: 0.875rem;
  color: #6b7280;
}

.meta-value {
  font-weight: 500;
}

.vote-button {
  width: 100%;
  padding: 0.75rem;
  background: #6366f1;
  color: white;
  border: none;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
}

.vote-button:hover {
  background: #4f46e5;
}

.vote-info {
  padding: 0.75rem;
  background: #f3f4f6;
  border-radius: 0.375rem;
  text-align: center;
  color: #6b7280;
}

.upcoming-polls {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.upcoming-poll {
  background: white;
  padding: 1rem;
  border-radius: 0.375rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 0.5rem;
  padding: 2rem;
  max-width: 600px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
}

.allocation-form {
  margin: 2rem 0;
}

.allocation-item {
  margin-bottom: 1.5rem;
}

.allocation-item label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}

.currency-name {
  flex: 1;
  font-weight: 500;
}

.allocation-item input {
  width: 80px;
  padding: 0.375rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  text-align: right;
}

.percentage {
  color: #6b7280;
}

.range-info {
  font-size: 0.875rem;
  color: #6b7280;
  margin-left: 1rem;
}

.total-row {
  display: flex;
  justify-content: space-between;
  padding: 1rem;
  background: #f3f4f6;
  border-radius: 0.375rem;
  font-weight: 600;
  margin-top: 1rem;
}

.total-row.invalid {
  background: #fee2e2;
  color: #991b1b;
}

.modal-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

.cancel-button, .submit-button {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  border: none;
}

.cancel-button {
  background: #e5e7eb;
  color: #4b5563;
}

.submit-button {
  background: #6366f1;
  color: white;
}

.submit-button:disabled {
  background: #9ca3af;
  cursor: not-allowed;
}
</style>
/**
 * Example integration of GCU Voting Dashboard
 * 
 * This file demonstrates how to integrate the GCU voting dashboard
 * into your existing Laravel application.
 */

// For Vue 3 integration in Laravel
import { createApp } from 'vue';
import GCUVotingDashboard from './GCUVotingDashboard.vue';

// Option 1: Mount as standalone component
const mountVotingDashboard = () => {
    const votingEl = document.getElementById('gcu-voting-dashboard');
    if (votingEl) {
        createApp(GCUVotingDashboard).mount(votingEl);
    }
};

// Option 2: Register as global component in existing Vue app
const registerGlobalComponent = (app) => {
    app.component('gcu-voting-dashboard', GCUVotingDashboard);
};

// Option 3: Use in a blade template
// In your blade file:
// <div id="gcu-voting-dashboard"></div>
// @push('scripts')
//     @vite('resources/js/voting-app.js')
// @endpush

// Example Blade template usage:
/*
@extends('layouts.app')

@section('content')
    <div class="container">
        <div id="gcu-voting-dashboard"></div>
    </div>
@endsection

@push('scripts')
    <script>
        // Initialize with auth token
        axios.defaults.headers.common['Authorization'] = 'Bearer ' + document.querySelector('meta[name="api-token"]').content;
    </script>
@endpush
*/

// Example API integration without Vue
const VotingAPI = {
    // Get active polls
    async getActivePolls() {
        const response = await fetch('/api/voting/polls', {
            headers: {
                'Authorization': `Bearer ${this.getToken()}`,
                'Accept': 'application/json'
            }
        });
        return response.json();
    },

    // Submit vote
    async submitVote(pollUuid, allocations) {
        const response = await fetch(`/api/voting/polls/${pollUuid}/vote`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.getToken()}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ allocations })
        });
        return response.json();
    },

    // Get dashboard data
    async getDashboard() {
        const response = await fetch('/api/voting/dashboard', {
            headers: {
                'Authorization': `Bearer ${this.getToken()}`,
                'Accept': 'application/json'
            }
        });
        return response.json();
    },

    getToken() {
        // Get token from meta tag, localStorage, or other source
        return document.querySelector('meta[name="api-token"]')?.content || 
               localStorage.getItem('api_token');
    }
};

// Example React component wrapper
/*
import React, { useEffect, useState } from 'react';

const GCUVotingDashboardReact = () => {
    const [dashboard, setDashboard] = useState(null);
    const [activePolls, setActivePolls] = useState([]);
    
    useEffect(() => {
        loadData();
    }, []);
    
    const loadData = async () => {
        try {
            const [dashboardData, pollsData] = await Promise.all([
                VotingAPI.getDashboard(),
                VotingAPI.getActivePolls()
            ]);
            setDashboard(dashboardData.data);
            setActivePolls(pollsData.data);
        } catch (error) {
            console.error('Error loading voting data:', error);
        }
    };
    
    const handleVote = async (pollUuid, allocations) => {
        try {
            await VotingAPI.submitVote(pollUuid, allocations);
            // Refresh data
            loadData();
        } catch (error) {
            console.error('Error submitting vote:', error);
        }
    };
    
    // Render your React components here
    return (
        <div className="gcu-voting-dashboard">
            // Your React UI here
        </div>
    );
};
*/

export { mountVotingDashboard, registerGlobalComponent, VotingAPI };
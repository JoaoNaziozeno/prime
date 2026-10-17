import { defineStore } from 'pinia';
import cashService from '../services/cashService';

export const useCashStore = defineStore('cash', {
  state: () => ({
    registers: [],
    currentSession: null,
    currentSummary: null,
    hasActiveSession: false,
    centralSession: null,
    centralSummary: null,
    hasCentralSession: false,
    pendingTransfersToCentral: [],
    recentMovements: [],
    loading: false,
    error: null,
  }),

  getters: {
    isSessionOpen: (state) => state.hasActiveSession && state.currentSession?.status === 'open',
    currentCashBalance: (state) => state.currentSummary?.current_cash_balance || 0,
    isOverDrawerLimit: (state) => state.currentSummary?.is_over_limit || false,
    drawerLimitUsage: (state) => state.currentSummary?.limit_usage_percent || 0,
    pendingBleedsTotal: (state) => state.currentSummary?.pending_bleeds || 0,
  },

  actions: {
    async fetchRegisters() {
      try {
        const response = await cashService.getRegisters();
        this.registers = response.data;
        return this.registers;
      } catch (err) {
        this.error = err.response?.data?.error || err.message;
        throw err;
      }
    },

    async fetchCurrentSession() {
      this.loading = true;
      try {
        const response = await cashService.getCurrentSession();
        this.hasActiveSession = response.data.has_active_session;
        this.currentSession = response.data.session;
        this.currentSummary = response.data.summary;
        if (this.currentSession) {
          await this.fetchMovements(this.currentSession.id);
        }
      } catch (err) {
        this.error = err.response?.data?.error || err.message;
      } finally {
        this.loading = false;
      }
    },

    async fetchCentralSession() {
      try {
        const response = await cashService.getCentralSession();
        this.hasCentralSession = response.data.has_active_session;
        this.centralSession = response.data.session;
        this.centralSummary = response.data.summary;
        if (this.centralSession) {
          const resPending = await cashService.getPendingIncomingTransfers(this.centralSession.id);
          this.pendingTransfersToCentral = resPending.data;
        }
      } catch (err) {
        console.error('Erro ao buscar caixa central:', err);
      }
    },

    async fetchMovements(sessionId) {
      try {
        const response = await cashService.getSessionMovements(sessionId);
        this.recentMovements = response.data;
      } catch (err) {
        console.error('Erro ao buscar movimentos da sessão:', err);
      }
    },

    async openSession(cashRegisterId, openingBalance) {
      this.loading = true;
      try {
        const res = await cashService.openSession(cashRegisterId, openingBalance);
        await this.fetchCurrentSession();
        await this.fetchRegisters();
        return res.data;
      } catch (err) {
        this.error = err.response?.data?.error || err.message;
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async closeSession(sessionId, reportedBalance, denominations, notes) {
      this.loading = true;
      try {
        const res = await cashService.closeSession(sessionId, reportedBalance, denominations, notes);
        await this.fetchCurrentSession();
        await this.fetchRegisters();
        return res.data;
      } catch (err) {
        this.error = err.response?.data?.error || err.message;
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async receiveOrderPayment(orderId, splits) {
      if (!this.currentSession) {
        throw new Error('Nenhuma sessão de caixa aberta para receber pagamento.');
      }
      this.loading = true;
      try {
        const res = await cashService.receiveOrderPayment(this.currentSession.id, orderId, splits);
        await this.fetchCurrentSession();
        return res.data;
      } catch (err) {
        this.error = err.response?.data?.error || err.message;
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async requestBleedToCentral(amount, notes) {
      if (!this.currentSession) {
        throw new Error('Nenhuma sessão aberta.');
      }
      if (!this.centralSession) {
        await this.fetchCentralSession();
        if (!this.centralSession) {
          throw new Error('O Caixa Central precisa estar aberto para receber a sangria.');
        }
      }

      this.loading = true;
      try {
        const res = await cashService.requestTransfer({
          source_session_id: this.currentSession.id,
          destination_session_id: this.centralSession.id,
          amount,
          transfer_type: 'bleed_to_central',
          notes,
        });
        await this.fetchCurrentSession();
        await this.fetchCentralSession();
        return res.data;
      } catch (err) {
        this.error = err.response?.data?.error || err.message;
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async acceptTransfer(transferId, notes) {
      this.loading = true;
      try {
        const res = await cashService.acceptTransfer(transferId, notes);
        await this.fetchCentralSession();
        await this.fetchCurrentSession();
        return res.data;
      } catch (err) {
        this.error = err.response?.data?.error || err.message;
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async rejectTransfer(transferId, reason) {
      this.loading = true;
      try {
        const res = await cashService.rejectTransfer(transferId, reason);
        await this.fetchCentralSession();
        return res.data;
      } catch (err) {
        this.error = err.response?.data?.error || err.message;
        throw err;
      } finally {
        this.loading = false;
      }
    },
  },
});

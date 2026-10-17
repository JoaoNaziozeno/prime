import api from './api';

export const cashService = {
  // Caixas Físicos/Lógicos
  getRegisters() {
    return api.get('/cash-registers');
  },

  createRegister(data) {
    return api.post('/cash-registers', data);
  },

  updateRegister(id, data) {
    return api.put(`/cash-registers/${id}`, data);
  },

  // Sessões de Caixa
  getCurrentSession() {
    return api.get('/cash/session/current');
  },

  getCentralSession() {
    return api.get('/cash/session/central');
  },

  openSession(cashRegisterId, openingBalance) {
    return api.post('/cash/session/open', {
      cash_register_id: cashRegisterId,
      opening_balance: openingBalance,
    });
  },

  closeSession(sessionId, reportedBalance, denominations = [], notes = '') {
    return api.post(`/cash/session/${sessionId}/close`, {
      reported_cash_balance: reportedBalance,
      denominations,
      notes,
    });
  },

  getSessionMovements(sessionId) {
    return api.get(`/cash/session/${sessionId}/movements`);
  },

  // Movimentações & Recebimentos de O.S
  receiveOrderPayment(sessionId, orderOfServiceId, splits) {
    return api.post(`/cash/session/${sessionId}/receive-os`, {
      order_of_service_id: orderOfServiceId,
      splits,
    });
  },

  registerPettyExpense(sessionId, amount, description, receiptNumber = null) {
    return api.post(`/cash/session/${sessionId}/petty-expense`, {
      amount,
      description,
      receipt_number: receiptNumber,
    });
  },

  registerSupply(sessionId, amount, description) {
    return api.post(`/cash/session/${sessionId}/supply`, {
      amount,
      description,
    });
  },

  // Transferências e Sangrias com Duplo Aceite
  getTransfers(params = {}) {
    return api.get('/cash/transfers', { params });
  },

  requestTransfer(data) {
    return api.post('/cash/transfers', data);
  },

  acceptTransfer(transferId, notes = '') {
    return api.post(`/cash/transfers/${transferId}/accept`, { notes });
  },

  rejectTransfer(transferId, reason) {
    return api.post(`/cash/transfers/${transferId}/reject`, { reason });
  },

  getPendingIncomingTransfers(destinationSessionId) {
    return api.get(`/cash/transfers/pending-incoming/${destinationSessionId}`);
  },

  // Métodos de Pagamento cadastrados
  getPaymentMethods() {
    return api.get('/payment-methods');
  },

  // Ordens de Serviço abertas para recebimento
  getPendingOrders() {
    return api.get('/orders', { params: { per_page: 50 } });
  },
};

export default cashService;

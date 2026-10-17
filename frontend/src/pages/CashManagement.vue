<script setup>
import { ref, onMounted, computed } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useCashStore } from '../stores/cash';
import cashService from '../services/cashService';
import Sidebar from '../components/Sidebar.vue';

const authStore = useAuthStore();
const cashStore = useCashStore();

// Controle de Abas: 'session' | 'central' | 'transfers'
const activeTab = ref('session');

// Estados de carregamento e mensagens
const loading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

// Modais
const showOpenSessionModal = ref(false);
const showReceiveOsModal = ref(false);
const showBleedModal = ref(false);
const showBlindCloseModal = ref(false);
const showPettyExpenseModal = ref(false);
const showSupplyModal = ref(false);
const showNewRegisterModal = ref(false);
const showRejectModal = ref(false);

// Dados para formulários
const openForm = ref({
  cash_register_id: '',
  opening_balance: 0,
});

const bleedForm = ref({
  amount: 0,
  notes: '',
});

const pettyExpenseForm = ref({
  amount: 0,
  description: '',
  receipt_number: '',
});

const supplyForm = ref({
  amount: 0,
  description: '',
});

const newRegisterForm = ref({
  name: '',
  type: 'user_station',
  is_central: false,
  max_cash_limit: 2000,
});

// Formulário de Fechamento Cego com Denominações
const blindCloseNotes = ref('');
const denominations = ref([
  { denomination: 200, label: 'R$ 200,00', quantity: 0 },
  { denomination: 100, label: 'R$ 100,00', quantity: 0 },
  { denomination: 50,  label: 'R$ 50,00',  quantity: 0 },
  { denomination: 20,  label: 'R$ 20,00',  quantity: 0 },
  { denomination: 10,  label: 'R$ 10,00',  quantity: 0 },
  { denomination: 5,   label: 'R$ 5,00',   quantity: 0 },
  { denomination: 2,   label: 'R$ 2,00',   quantity: 0 },
  { denomination: 1,   label: 'R$ 1,00 (Moeda)', quantity: 0 },
  { denomination: 0.50, label: 'R$ 0,50 (Moeda)', quantity: 0 },
  { denomination: 0.25, label: 'R$ 0,25 (Moeda)', quantity: 0 },
  { denomination: 0.10, label: 'R$ 0,10 (Moeda)', quantity: 0 },
  { denomination: 0.05, label: 'R$ 0,05 (Moeda)', quantity: 0 },
]);

const calculatedReportedBalance = computed(() => {
  return denominations.value.reduce((acc, item) => {
    return acc + (item.denomination * (parseInt(item.quantity) || 0));
  }, 0);
});

// Modal de Recebimento de O.S com Split
const ordersList = ref([]);
const paymentMethodsList = ref([
  { id: 'dinheiro', name: 'Dinheiro em Espécie', code: 'cash' },
  { id: 'pix', name: 'PIX (Chave / QR Code)', code: 'pix' },
  { id: 'cartao_credito', name: 'Cartão de Crédito (POS)', code: 'credit_card' },
  { id: 'cartao_debito', name: 'Cartão de Débito (POS)', code: 'debit_card' },
  { id: 'boleto', name: 'Boleto / A Prazo', code: 'bank_slip' },
]);

const receiveOsForm = ref({
  order_id: '',
  selectedOrder: null,
  splits: [
    { payment_method_id: '', amount: 0, metadata: { nsu: '', card_brand: '' } }
  ]
});

// Modal de Rejeição de Transferência
const rejectingTransferId = ref(null);
const rejectionReason = ref('');

// Transferências Gerais
const transfersList = ref([]);
const filterTransferStatus = ref('');

// Helpers de Notificação
const showSuccess = (msg) => {
  successMessage.value = msg;
  errorMessage.value = '';
  setTimeout(() => { successMessage.value = ''; }, 5000);
};

const showError = (msg) => {
  errorMessage.value = msg;
  successMessage.value = '';
  setTimeout(() => { errorMessage.value = ''; }, 6000);
};

// Formatação Monetária
const formatCurrency = (val) => {
  const num = parseFloat(val) || 0;
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(num);
};

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// Ações do Ciclo de Vida
onMounted(async () => {
  loading.value = true;
  try {
    await Promise.all([
      cashStore.fetchRegisters(),
      cashStore.fetchCurrentSession(),
      cashStore.fetchCentralSession(),
      loadTransfers(),
      loadPaymentMethods(),
      loadPendingOrders(),
    ]);

    // Seleciona o primeiro caixa de usuário disponível por padrão
    const userRegs = cashStore.registers.filter(r => !r.is_central && r.is_active);
    if (userRegs.length > 0) {
      openForm.value.cash_register_id = userRegs[0].id;
    }
  } catch (err) {
    console.error('Erro na carga inicial:', err);
  } finally {
    loading.value = false;
  }
});

const loadTransfers = async () => {
  try {
    const params = {};
    if (filterTransferStatus.value) {
      params.status = filterTransferStatus.value;
    }
    const res = await cashService.getTransfers(params);
    transfersList.value = res.data.data || res.data || [];
  } catch (err) {
    console.error('Erro ao buscar transferências:', err);
  }
};

const loadPaymentMethods = async () => {
  try {
    const res = await cashService.getPaymentMethods();
    if (res.data && res.data.length > 0) {
      paymentMethodsList.value = res.data;
    }
  } catch (err) {
    // Usa métodos padrão se tabela vazia
  }
};

const loadPendingOrders = async () => {
  try {
    const res = await cashService.getPendingOrders();
    ordersList.value = res.data?.data || res.data || [];
  } catch (err) {
    console.error('Erro ao buscar O.S:', err);
  }
};

// --- Handlers de Ação ---

// 1. Abrir Caixa
const handleOpenSession = async () => {
  if (!openForm.value.cash_register_id) {
    showError('Selecione um ponto de atendimento.');
    return;
  }
  loading.value = true;
  try {
    await cashStore.openSession(openForm.value.cash_register_id, openForm.value.opening_balance);
    showOpenSessionModal.value = false;
    showSuccess('Caixa aberto com sucesso! Bom turno.');
  } catch (err) {
    showError(err.response?.data?.error || err.message);
  } finally {
    loading.value = false;
  }
};

// 2. Sangria para o Caixa Central
const handleRequestBleed = async () => {
  const amt = parseFloat(bleedForm.value.amount);
  if (!amt || amt <= 0) {
    showError('Informe um valor válido para a sangria.');
    return;
  }
  loading.value = true;
  try {
    const res = await cashStore.requestBleedToCentral(amt, bleedForm.value.notes);
    showBleedModal.value = false;
    bleedForm.value.amount = 0;
    bleedForm.value.notes = '';
    showSuccess(`Sangria de ${formatCurrency(amt)} solicitada sob protocolo ${res.transfer?.protocol_number}! Entregue o envelope ao Administrador.`);
    await loadTransfers();
  } catch (err) {
    showError(err.response?.data?.error || err.message);
  } finally {
    loading.value = false;
  }
};

// 3. Fechamento Cego
const handleBlindClose = async () => {
  const reportedTotal = calculatedReportedBalance.value;
  loading.value = true;
  try {
    const res = await cashStore.closeSession(
      cashStore.currentSession.id,
      reportedTotal,
      denominations.value,
      blindCloseNotes.value
    );
    showBlindCloseModal.value = false;
    
    // Reseta contadores
    denominations.value.forEach(d => { d.quantity = 0; });
    blindCloseNotes.value = '';

    const diff = parseFloat(res.difference) || 0;
    if (diff === 0) {
      showSuccess('Caixa fechado com sucesso! Saldo apurado 100% exato.');
    } else if (diff > 0) {
      showSuccess(`Caixa fechado. Houve SOBRA de caixa de ${formatCurrency(diff)}.`);
    } else {
      showError(`Caixa fechado. Houve FALTA de caixa de ${formatCurrency(Math.abs(diff))}. Verifique com o gestor.`);
    }
  } catch (err) {
    showError(err.response?.data?.error || err.message);
  } finally {
    loading.value = false;
  }
};

// 4. Receber O.S com Split
const onSelectOrder = () => {
  const order = ordersList.value.find(o => o.id === receiveOsForm.value.order_id);
  receiveOsForm.value.selectedOrder = order;
  if (order) {
    const total = parseFloat(order.approved_amount || order.estimated_cost || 0);
    receiveOsForm.value.splits = [
      { payment_method_id: paymentMethodsList.value[0]?.id || '', amount: total, metadata: {} }
    ];
  }
};

const addSplitRow = () => {
  receiveOsForm.value.splits.push({
    payment_method_id: paymentMethodsList.value[0]?.id || '',
    amount: 0,
    metadata: {}
  });
};

const removeSplitRow = (idx) => {
  if (receiveOsForm.value.splits.length > 1) {
    receiveOsForm.value.splits.splice(idx, 1);
  }
};

const handleReceiveOs = async () => {
  if (!receiveOsForm.value.order_id) {
    showError('Selecione uma Ordem de Serviço.');
    return;
  }
  loading.value = true;
  try {
    await cashStore.receiveOrderPayment(receiveOsForm.value.order_id, receiveOsForm.value.splits);
    showReceiveOsModal.value = false;
    showSuccess('Recebimento de O.S liquidado com sucesso!');
    await cashStore.fetchCurrentSession();
    await loadPendingOrders();
  } catch (err) {
    showError(err.response?.data?.error || err.message);
  } finally {
    loading.value = false;
  }
};

// 5. Despesa Miúda
const handlePettyExpense = async () => {
  loading.value = true;
  try {
    await cashService.registerPettyExpense(
      cashStore.currentSession.id,
      pettyExpenseForm.value.amount,
      pettyExpenseForm.value.description,
      pettyExpenseForm.value.receipt_number
    );
    showPettyExpenseModal.value = false;
    pettyExpenseForm.value.amount = 0;
    pettyExpenseForm.value.description = '';
    pettyExpenseForm.value.receipt_number = '';
    showSuccess('Despesa de balcão lançada com sucesso.');
    await cashStore.fetchCurrentSession();
  } catch (err) {
    showError(err.response?.data?.error || err.message);
  } finally {
    loading.value = false;
  }
};

// 6. Aceite de Sangria pelo ADM
const handleAcceptTransfer = async (transferId) => {
  loading.value = true;
  try {
    await cashStore.acceptTransfer(transferId, 'Conferido fisicamente e aceito');
    showSuccess('Transferência confirmada! Valores creditados no Caixa Central com sucesso.');
    await loadTransfers();
  } catch (err) {
    showError(err.response?.data?.error || err.message);
  } finally {
    loading.value = false;
  }
};

// 7. Recusa de Sangria pelo ADM
const openRejectModal = (transferId) => {
  rejectingTransferId.value = transferId;
  rejectionReason.value = '';
  showRejectModal.value = true;
};

const handleConfirmReject = async () => {
  if (!rejectionReason.value) {
    showError('Informe o motivo da divergência / recusa.');
    return;
  }
  loading.value = true;
  try {
    await cashStore.rejectTransfer(rejectingTransferId.value, rejectionReason.value);
    showRejectModal.value = false;
    showSuccess('Transferência contestada e devolvida ao operador.');
    await loadTransfers();
  } catch (err) {
    showError(err.response?.data?.error || err.message);
  } finally {
    loading.value = false;
  }
};

// 8. Cadastrar Novo Ponto de Caixa
const handleCreateRegister = async () => {
  loading.value = true;
  try {
    await cashService.createRegister(newRegisterForm.value);
    showNewRegisterModal.value = false;
    newRegisterForm.value.name = '';
    showSuccess('Novo caixa cadastrado com sucesso!');
    await cashStore.fetchRegisters();
  } catch (err) {
    showError(err.response?.data?.error || err.message);
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="d-flex min-vh-100 bg-light">
    <!-- Sidebar -->
    <Sidebar />

    <!-- Main Content -->
    <main class="flex-grow-1 p-4 overflow-auto">
      <!-- Top Alert Messages -->
      <div v-if="successMessage" class="alert alert-success alert-dismissible fade show shadow-sm d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div>{{ successMessage }}</div>
        <button type="button" class="btn-close ms-auto" @click="successMessage = ''"></button>
      </div>

      <div v-if="errorMessage" class="alert alert-danger alert-dismissible fade show shadow-sm d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>{{ errorMessage }}</div>
        <button type="button" class="btn-close ms-auto" @click="errorMessage = ''"></button>
      </div>

      <!-- Alerta Amigável de Teto de Gaveta -->
      <div v-if="cashStore.isOverDrawerLimit" class="alert alert-warning border-start border-warning border-4 shadow-sm mb-4">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <div class="p-2 bg-warning-subtle text-warning rounded-circle">
              <i class="bi bi-shield-exclamation fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Alerta de Segurança Física: Teto de Gaveta Atingido!</h6>
              <p class="mb-0 text-muted small">
                O saldo em dinheiro desta gaveta ({{ formatCurrency(cashStore.currentCashBalance) }}) atingiu 
                {{ cashStore.drawerLimitUsage }}% do teto configurado. É recomendável despachar uma sangria ao Caixa Central.
              </p>
            </div>
          </div>
          <button @click="showBleedModal = true" class="btn btn-warning btn-sm fw-bold px-3 shadow-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i> Fazer Sangria Agora
          </button>
        </div>
      </div>

      <!-- Header & Navegação em Abas -->
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <h2 class="fw-bold text-dark mb-1 font-headline d-flex align-items-center gap-2">
            <i class="bi bi-cash-coin text-primary"></i> Caixa & Tesouraria Multi-Nível
          </h2>
          <p class="text-muted small mb-0">Gestão integrada de recebimentos de O.S, sessões de operadores e cofre central.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
          <div class="btn-group bg-white p-1 rounded-pill shadow-sm border" role="group">
            <button
              @click="activeTab = 'session'"
              :class="['btn btn-sm rounded-pill px-3 fw-medium', activeTab === 'session' ? 'btn-primary text-white' : 'btn-light text-secondary']"
            >
              <i class="bi bi-wallet2 me-1"></i> Minha Sessão (Balcão)
            </button>
            <button
              @click="activeTab = 'central'"
              :class="['btn btn-sm rounded-pill px-3 fw-medium', activeTab === 'central' ? 'btn-primary text-white' : 'btn-light text-secondary']"
            >
              <i class="bi bi-bank me-1"></i> Caixa Central (ADM)
              <span v-if="cashStore.pendingTransfersToCentral.length > 0" class="badge bg-danger rounded-pill ms-1">
                {{ cashStore.pendingTransfersToCentral.length }}
              </span>
            </button>
            <button
              @click="activeTab = 'transfers'; loadTransfers();"
              :class="['btn btn-sm rounded-pill px-3 fw-medium', activeTab === 'transfers' ? 'btn-primary text-white' : 'btn-light text-secondary']"
            >
              <i class="bi bi-arrow-left-right me-1"></i> Transferências & Sangrias
            </button>
          </div>
        </div>
      </div>

      <!-- ================= ABA 1: MINHA SESSÃO / OPERADOR ================= -->
      <section v-if="activeTab === 'session'">
        <!-- Caso 1: Caixa Fechado -->
        <div v-if="!cashStore.isSessionOpen" class="card border-0 shadow-sm text-center py-5 px-4 rounded-4 bg-white">
          <div class="mx-auto mb-3 p-4 bg-primary-subtle text-primary rounded-circle" style="width: 80px; height: 80px; display: grid; place-items: center;">
            <i class="bi bi-lock-fill fs-1"></i>
          </div>
          <h4 class="fw-bold text-dark font-headline mb-2">Seu Caixa está Fechado</h4>
          <p class="text-muted mx-auto mb-4" style="max-width: 480px;">
            Para começar a receber pagamentos de Ordens de Serviço, emitir comprovantes e movimentar valores, abra sua sessão de turno informando o fundo de troco inicial.
          </p>
          <div>
            <button @click="showOpenSessionModal = true" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold">
              <i class="bi bi-unlock-fill me-1"></i> Abrir Meu Caixa Agora
            </button>
          </div>
        </div>

        <!-- Caso 2: Caixa Aberto -->
        <div v-else>
          <!-- Info Banner da Sessão Ativa -->
          <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
            <div class="card-body p-4">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                  <div class="p-3 bg-success-subtle text-success rounded-3">
                    <i class="bi bi-shop fs-3"></i>
                  </div>
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <h5 class="fw-bold text-dark mb-0 font-headline">{{ cashStore.currentSession.cash_register?.name }}</h5>
                      <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">Aberto</span>
                    </div>
                    <small class="text-muted">
                      Operador: <strong>{{ authStore.user?.name || 'Você' }}</strong> | Aberto em: {{ formatDateTime(cashStore.currentSession.opened_at) }}
                    </small>
                  </div>
                </div>

                <!-- Botões de Ação do Operador -->
                <div class="d-flex flex-wrap gap-2">
                  <button @click="showReceiveOsModal = true" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm fw-bold">
                    <i class="bi bi-receipt me-1"></i> Receber O.S (Split)
                  </button>
                  <button @click="showBleedModal = true" class="btn btn-outline-warning btn-sm rounded-pill px-3 fw-bold">
                    <i class="bi bi-arrow-up-right-circle me-1"></i> Solicitar Sangria
                  </button>
                  <button @click="showPettyExpenseModal = true" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-cash-stack me-1"></i> Despesa de Balcão
                  </button>
                  <button @click="showBlindCloseModal = true" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold">
                    <i class="bi bi-lock me-1"></i> Fechamento Cego
                  </button>
                </div>
              </div>

              <!-- Cartões de Métricas Financeiras -->
              <div class="row g-3">
                <div class="col-md-3">
                  <div class="p-3 rounded-3 bg-light border">
                    <span class="text-muted small d-block">Saldo em Dinheiro (Gaveta)</span>
                    <span class="fs-4 fw-bold text-success">{{ formatCurrency(cashStore.currentCashBalance) }}</span>
                    <div class="progress mt-2" style="height: 6px;" title="Uso do limite de gaveta">
                      <div
                        class="progress-bar"
                        :class="cashStore.isOverDrawerLimit ? 'bg-danger' : 'bg-success'"
                        role="progressbar"
                        :style="{ width: Math.min(100, cashStore.drawerLimitUsage) + '%' }"
                      ></div>
                    </div>
                    <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">
                      Teto: {{ formatCurrency(cashStore.currentSummary?.max_cash_limit) }} ({{ cashStore.drawerLimitUsage }}%)
                    </small>
                  </div>
                </div>

                <div class="col-md-3">
                  <div class="p-3 rounded-3 bg-light border">
                    <span class="text-muted small d-block">Fundo de Troco Inicial</span>
                    <span class="fs-4 fw-bold text-dark">{{ formatCurrency(cashStore.currentSession.opening_balance) }}</span>
                    <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">Informado na abertura</small>
                  </div>
                </div>

                <div class="col-md-3">
                  <div class="p-3 rounded-3 bg-light border">
                    <span class="text-muted small d-block">Total de Entradas (Turno)</span>
                    <span class="fs-4 fw-bold text-primary">{{ formatCurrency(cashStore.currentSummary?.total_inflows) }}</span>
                    <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">
                      {{ cashStore.currentSummary?.by_payment_method?.length || 0 }} formas de pagamento
                    </small>
                  </div>
                </div>

                <div class="col-md-3">
                  <div class="p-3 rounded-3 bg-light border">
                    <span class="text-muted small d-block">Sangrias em Trânsito</span>
                    <span class="fs-4 fw-bold text-warning">{{ formatCurrency(cashStore.pendingBleedsTotal) }}</span>
                    <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">Aguardando aceite do ADM</small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabela de Movimentações da Sessão -->
          <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
              <h6 class="fw-bold text-dark mb-0 font-headline d-flex align-items-center gap-2">
                <i class="bi bi-list-columns text-primary"></i> Lançamentos Desta Sessão
              </h6>
              <span class="badge bg-light text-secondary border">{{ cashStore.recentMovements.length }} registros</span>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                  <tr>
                    <th>Horário</th>
                    <th>Tipo</th>
                    <th>Categoria</th>
                    <th>Descrição</th>
                    <th>Forma</th>
                    <th class="text-end">Valor</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="cashStore.recentMovements.length === 0">
                    <td colspan="6" class="text-center py-4 text-muted small">Nenhum movimento registrado nesta sessão até o momento.</td>
                  </tr>
                  <tr v-for="m in cashStore.recentMovements" :key="m.id">
                    <td class="font-monospace small">{{ formatDateTime(m.created_at) }}</td>
                    <td>
                      <span :class="['badge rounded-pill', m.type === 'inflow' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger']">
                        {{ m.type === 'inflow' ? '+ Entrada' : '- Saída' }}
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark border font-monospace small">
                        {{ m.category }}
                      </span>
                    </td>
                    <td class="small fw-medium">{{ m.description }}</td>
                    <td>
                      <span class="badge bg-secondary-subtle text-secondary small">
                        {{ m.payment_method?.name || 'Interno' }}
                      </span>
                    </td>
                    <td :class="['text-end fw-bold font-monospace', m.type === 'inflow' ? 'text-success' : 'text-danger']">
                      {{ m.type === 'inflow' ? '+' : '-' }} {{ formatCurrency(m.amount) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- ================= ABA 2: CAIXA CENTRAL / TESOURARIA ================= -->
      <section v-if="activeTab === 'central'">
        <!-- Status Geral do Cofre Central -->
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-4 h-100">
              <span class="text-white-50 text-uppercase fw-bold letter-spacing-xs small">Cofre Geral / Tesouraria</span>
              <h2 class="fw-bold mb-1 font-headline mt-2">{{ formatCurrency(cashStore.centralSummary?.current_cash_balance || 0) }}</h2>
              <small class="text-white-50">Disponível no Caixa Central da Empresa</small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 h-100">
              <span class="text-muted text-uppercase fw-bold small">Sangrias Aguardando Aceite</span>
              <h2 class="fw-bold mb-1 text-warning font-headline mt-2">{{ cashStore.pendingTransfersToCentral.length }}</h2>
              <small class="text-muted">Envelopes físicos em conferência com o ADM</small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 h-100 d-flex flex-column justify-content-between">
              <div>
                <span class="text-muted text-uppercase fw-bold small">Pontos de Caixa Cadastrados</span>
                <h2 class="fw-bold mb-1 text-dark font-headline mt-2">{{ cashStore.registers.length }}</h2>
              </div>
              <button @click="showNewRegisterModal = true" class="btn btn-outline-primary btn-sm rounded-pill fw-bold">
                <i class="bi bi-plus-circle me-1"></i> Novo Ponto de Caixa
              </button>
            </div>
          </div>
        </div>

        <!-- Fila de Sangrias Pendentes (Duplo Aceite do ADM) -->
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
          <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-inbox-fill text-warning fs-5"></i>
              <h6 class="fw-bold text-dark mb-0 font-headline">Fila de Sangrias Pendentes de Aceite (Duplo Aceite)</h6>
            </div>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">
              {{ cashStore.pendingTransfersToCentral.length }} pendentes
            </span>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light small text-uppercase text-muted">
                <tr>
                  <th>Protocolo</th>
                  <th>Origem (Operador)</th>
                  <th>Horário</th>
                  <th>Notas / Envelope</th>
                  <th>Valor</th>
                  <th class="text-end">Ações de Conferência</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="cashStore.pendingTransfersToCentral.length === 0">
                  <td colspan="6" class="text-center py-4 text-muted small">Nenhuma sangria pendente de aceite no momento.</td>
                </tr>
                <tr v-for="t in cashStore.pendingTransfersToCentral" :key="t.id">
                  <td>
                    <span class="badge bg-dark font-monospace">{{ t.protocol_number }}</span>
                  </td>
                  <td>
                    <strong>{{ t.source_session?.user?.name || 'Operador' }}</strong>
                    <small class="text-muted d-block">{{ t.source_session?.cash_register?.name }}</small>
                  </td>
                  <td class="font-monospace small">{{ formatDateTime(t.requested_at) }}</td>
                  <td class="small text-muted">{{ t.notes || 'Envelope padrão' }}</td>
                  <td class="fw-bold text-success font-monospace fs-6">{{ formatCurrency(t.amount) }}</td>
                  <td class="text-end">
                    <button @click="handleAcceptTransfer(t.id)" class="btn btn-success btn-sm rounded-pill px-3 me-2 fw-bold shadow-sm">
                      <i class="bi bi-check-lg me-1"></i> Confirmar & Receber
                    </button>
                    <button @click="openRejectModal(t.id)" class="btn btn-outline-danger btn-sm rounded-pill px-2">
                      <i class="bi bi-x-lg"></i> Recusar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Monitor de Caixas / Gavetas em Tempo Real -->
        <div class="card border-0 shadow-sm rounded-4 bg-white">
          <div class="card-header bg-white border-bottom p-3">
            <h6 class="fw-bold text-dark mb-0 font-headline d-flex align-items-center gap-2">
              <i class="bi bi-tv text-primary"></i> Monitor de Gavetas Operacionais em Tempo Real
            </h6>
          </div>
          <div class="p-3">
            <div class="row g-3">
              <div v-for="reg in cashStore.registers" :key="reg.id" class="col-md-4">
                <div class="p-3 rounded-4 border bg-light h-100 position-relative">
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge bg-white text-dark border">{{ reg.type_label }}</span>
                    <span :class="['badge rounded-pill', reg.active_session ? 'bg-success text-white' : 'bg-secondary text-white']">
                      {{ reg.active_session ? 'Em Turno' : 'Fechado' }}
                    </span>
                  </div>
                  <h6 class="fw-bold text-dark mb-1 font-headline">{{ reg.name }}</h6>
                  <div v-if="reg.active_session" class="mt-2">
                    <small class="text-muted d-block">Operador: <strong>{{ reg.active_session.operator?.name }}</strong></small>
                    <span class="fs-5 fw-bold text-success d-block mt-1 font-monospace">
                      {{ formatCurrency(reg.active_session.current_cash_balance) }}
                    </span>
                    <small class="text-muted d-block" style="font-size: 0.7rem;">
                      Aberto às {{ formatDateTime(reg.active_session.opened_at) }}
                    </small>
                  </div>
                  <div v-else class="text-muted small mt-3 py-2">
                    Nenhum operador com caixa aberto.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ================= ABA 3: HISTÓRICO DE TRANSFERÊNCIAS ================= -->
      <section v-if="activeTab === 'transfers'">
        <div class="card border-0 shadow-sm rounded-4 bg-white">
          <div class="card-header bg-white border-bottom p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h6 class="fw-bold text-dark mb-0 font-headline d-flex align-items-center gap-2">
              <i class="bi bi-journal-text text-primary"></i> Extrato de Sangrias e Transferências
            </h6>
            <div class="d-flex gap-2">
              <select v-model="filterTransferStatus" @change="loadTransfers" class="form-select form-select-sm rounded-pill" style="width: 180px;">
                <option value="">Todos os Status</option>
                <option value="pending">Pendentes</option>
                <option value="accepted">Aceitas</option>
                <option value="rejected">Rejeitadas</option>
              </select>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light small text-uppercase text-muted">
                <tr>
                  <th>Protocolo</th>
                  <th>Tipo</th>
                  <th>Origem</th>
                  <th>Destino</th>
                  <th>Valor</th>
                  <th>Data Solicitação</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="transfersList.length === 0">
                  <td colspan="7" class="text-center py-4 text-muted small">Nenhuma transferência encontrada.</td>
                </tr>
                <tr v-for="t in transfersList" :key="t.id">
                  <td>
                    <span class="badge bg-dark font-monospace">{{ t.protocol_number }}</span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border">{{ t.transfer_type }}</span>
                  </td>
                  <td class="small">{{ t.source_session?.cash_register?.name || '-' }}</td>
                  <td class="small">{{ t.destination_session?.cash_register?.name || '-' }}</td>
                  <td class="fw-bold font-monospace text-dark">{{ formatCurrency(t.amount) }}</td>
                  <td class="font-monospace small">{{ formatDateTime(t.requested_at) }}</td>
                  <td>
                    <span v-if="t.status === 'accepted'" class="badge bg-success-subtle text-success rounded-pill">Aceito</span>
                    <span v-else-if="t.status === 'pending'" class="badge bg-warning-subtle text-warning rounded-pill">Pendente</span>
                    <span v-else-if="t.status === 'rejected'" class="badge bg-danger-subtle text-danger rounded-pill">Rejeitado</span>
                    <span v-else class="badge bg-secondary rounded-pill">{{ t.status }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ================= MODAIS ================= -->

      <!-- 1. Modal Abrir Caixa -->
      <div v-if="showOpenSessionModal" class="modal-backdrop fade show"></div>
      <div v-if="showOpenSessionModal" class="modal fade show d-block" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title fw-bold font-headline"><i class="bi bi-unlock-fill text-primary me-2"></i>Abertura de Caixa</h5>
              <button type="button" class="btn-close" @click="showOpenSessionModal = false"></button>
            </div>
            <div class="modal-body p-4">
              <div class="mb-3">
                <label class="form-label small fw-bold">Ponto de Atendimento / Guichê</label>
                <select v-model="openForm.cash_register_id" class="form-select rounded-3">
                  <option v-for="r in cashStore.registers.filter(r => r.is_active)" :key="r.id" :value="r.id">
                    {{ r.name }} {{ r.is_central ? '(Cofre Central)' : '' }}
                  </option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-bold">Fundo de Troco Inicial (R$)</label>
                <div class="input-group">
                  <span class="input-group-text bg-light">R$</span>
                  <input v-model.number="openForm.opening_balance" type="number" step="0.01" min="0" class="form-control" placeholder="0,00" />
                </div>
                <small class="text-muted">Valor em dinheiro colocado na gaveta para iniciar as operações.</small>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-light rounded-pill px-3" @click="showOpenSessionModal = false">Cancelar</button>
              <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" @click="handleOpenSession" :disabled="loading">
                Confirmar Abertura
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Modal Solicitar Sangria -->
      <div v-if="showBleedModal" class="modal-backdrop fade show"></div>
      <div v-if="showBleedModal" class="modal fade show d-block" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title fw-bold font-headline text-warning"><i class="bi bi-arrow-up-right-circle-fill me-2"></i>Solicitar Sangria</h5>
              <button type="button" class="btn-close" @click="showBleedModal = false"></button>
            </div>
            <div class="modal-body p-4">
              <div class="p-3 bg-light rounded-3 mb-3 border">
                <span class="text-muted small d-block">Saldo Disponível em Gaveta</span>
                <span class="fs-5 fw-bold text-success">{{ formatCurrency(cashStore.currentCashBalance) }}</span>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-bold">Valor da Sangria (R$)</label>
                <div class="input-group">
                  <span class="input-group-text bg-light">R$</span>
                  <input v-model.number="bleedForm.amount" type="number" step="0.01" :max="cashStore.currentCashBalance" class="form-control" placeholder="0,00" />
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-bold">Observações / Número do Envelope</label>
                <input v-model="bleedForm.notes" type="text" class="form-control" placeholder="Ex: Envelope lacrado 102" />
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-light rounded-pill px-3" @click="showBleedModal = false">Cancelar</button>
              <button type="button" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm" @click="handleRequestBleed" :disabled="loading">
                Emitir Sangria
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Modal Fechamento Cego com Calculadora de Cédulas -->
      <div v-if="showBlindCloseModal" class="modal-backdrop fade show"></div>
      <div v-if="showBlindCloseModal" class="modal fade show d-block" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
              <div>
                <h5 class="modal-title fw-bold font-headline text-danger"><i class="bi bi-lock-fill me-2"></i>Fechamento Cego de Caixa</h5>
                <small class="text-muted">Conte as cédulas e moedas físicas na gaveta. O sistema fará a apuração automática da quebra/sobra.</small>
              </div>
              <button type="button" class="btn-close" @click="showBlindCloseModal = false"></button>
            </div>
            <div class="modal-body p-4">
              <div class="row g-2 mb-3">
                <div v-for="d in denominations" :key="d.denomination" class="col-md-4">
                  <div class="p-2 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                    <div>
                      <span class="fw-bold small d-block">{{ d.label }}</span>
                      <small class="text-muted">{{ formatCurrency(d.denomination * (d.quantity || 0)) }}</small>
                    </div>
                    <input v-model.number="d.quantity" type="number" min="0" class="form-control form-control-sm text-center" style="width: 70px;" placeholder="0" />
                  </div>
                </div>
              </div>

              <div class="p-3 bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-between mb-3">
                <span class="fw-bold">Total Físico Contado:</span>
                <span class="fs-4 fw-bold font-monospace">{{ formatCurrency(calculatedReportedBalance) }}</span>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-bold">Observações do Fechamento</label>
                <textarea v-model="blindCloseNotes" class="form-control" rows="2" placeholder="Ex: Troco repassado normalmente..."></textarea>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-light rounded-pill px-3" @click="showBlindCloseModal = false">Voltar</button>
              <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm" @click="handleBlindClose" :disabled="loading">
                Encerrar Turno Definitivamente
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 4. Modal Receber O.S com Split -->
      <div v-if="showReceiveOsModal" class="modal-backdrop fade show"></div>
      <div v-if="showReceiveOsModal" class="modal fade show d-block" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title fw-bold font-headline"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Recebimento de O.S (Split Multi-Meios)</h5>
              <button type="button" class="btn-close" @click="showReceiveOsModal = false"></button>
            </div>
            <div class="modal-body p-4">
              <div class="mb-3">
                <label class="form-label small fw-bold">Selecione a Ordem de Serviço</label>
                <select v-model="receiveOsForm.order_id" @change="onSelectOrder" class="form-select rounded-3">
                  <option value="">Selecione uma O.S pendente de pagamento...</option>
                  <option v-for="ord in ordersList" :key="ord.id" :value="ord.id">
                    O.S #{{ ord.reference_number || ord.id.substring(0,8) }} - {{ ord.customer?.name || 'Cliente' }} (Total: {{ formatCurrency(ord.approved_amount || ord.estimated_cost) }})
                  </option>
                </select>
              </div>

              <div v-if="receiveOsForm.selectedOrder" class="p-3 bg-light rounded-3 border mb-3">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="small text-muted">Valor Total da O.S:</span>
                  <span class="fw-bold fs-5 text-primary">
                    {{ formatCurrency(receiveOsForm.selectedOrder.approved_amount || receiveOsForm.selectedOrder.estimated_cost) }}
                  </span>
                </div>
              </div>

              <!-- Lista de Formas de Pagamento (Split) -->
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="small fw-bold">Formas de Pagamento Utilizadas</span>
                  <button @click="addSplitRow" type="button" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-2">
                    <i class="bi bi-plus"></i> Adicionar Meio
                  </button>
                </div>

                <div v-for="(split, idx) in receiveOsForm.splits" :key="idx" class="row g-2 align-items-center mb-2">
                  <div class="col-md-5">
                    <select v-model="split.payment_method_id" class="form-select form-select-sm">
                      <option v-for="pm in paymentMethodsList" :key="pm.id" :value="pm.id">
                        {{ pm.name }}
                      </option>
                    </select>
                  </div>
                  <div class="col-md-5">
                    <div class="input-group input-group-sm">
                      <span class="input-group-text">R$</span>
                      <input v-model.number="split.amount" type="number" step="0.01" class="form-control" placeholder="0,00" />
                    </div>
                  </div>
                  <div class="col-md-2 text-end">
                    <button @click="removeSplitRow(idx)" type="button" class="btn btn-outline-danger btn-sm rounded-circle p-1" :disabled="receiveOsForm.splits.length === 1">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-light rounded-pill px-3" @click="showReceiveOsModal = false">Cancelar</button>
              <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" @click="handleReceiveOs" :disabled="loading">
                Confirmar Recebimento
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 5. Modal Despesa de Balcão -->
      <div v-if="showPettyExpenseModal" class="modal-backdrop fade show"></div>
      <div v-if="showPettyExpenseModal" class="modal fade show d-block" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title fw-bold font-headline"><i class="bi bi-cash-stack text-secondary me-2"></i>Despesa Miúda de Balcão</h5>
              <button type="button" class="btn-close" @click="showPettyExpenseModal = false"></button>
            </div>
            <div class="modal-body p-4">
              <div class="mb-3">
                <label class="form-label small fw-bold">Valor da Despesa (R$)</label>
                <div class="input-group">
                  <span class="input-group-text bg-light">R$</span>
                  <input v-model.number="pettyExpenseForm.amount" type="number" step="0.01" class="form-control" placeholder="0,00" />
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-bold">Descrição / Finalidade</label>
                <input v-model="pettyExpenseForm.description" type="text" class="form-control" placeholder="Ex: Compra de café / Entrega motoboy" />
              </div>
              <div class="mb-3">
                <label class="form-label small fw-bold">Número do Comprovante / Cupom</label>
                <input v-model="pettyExpenseForm.receipt_number" type="text" class="form-control" placeholder="Opcional" />
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-light rounded-pill px-3" @click="showPettyExpenseModal = false">Cancelar</button>
              <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" @click="handlePettyExpense" :disabled="loading">
                Registrar Saída
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 6. Modal Recusar Transferência -->
      <div v-if="showRejectModal" class="modal-backdrop fade show"></div>
      <div v-if="showRejectModal" class="modal fade show d-block" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title fw-bold font-headline text-danger"><i class="bi bi-x-circle-fill me-2"></i>Recusar / Contestar Sangria</h5>
              <button type="button" class="btn-close" @click="showRejectModal = false"></button>
            </div>
            <div class="modal-body p-4">
              <label class="form-label small fw-bold">Motivo da Recusa / Divergência Física</label>
              <textarea v-model="rejectionReason" class="form-control" rows="3" placeholder="Ex: O envelope continha R$ 700 e não R$ 800 conforme solicitado..."></textarea>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-light rounded-pill px-3" @click="showRejectModal = false">Cancelar</button>
              <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold" @click="handleConfirmReject" :disabled="loading">
                Confirmar Recusa
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 7. Modal Novo Caixa -->
      <div v-if="showNewRegisterModal" class="modal-backdrop fade show"></div>
      <div v-if="showNewRegisterModal" class="modal fade show d-block" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title fw-bold font-headline"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Novo Ponto de Caixa</h5>
              <button type="button" class="btn-close" @click="showNewRegisterModal = false"></button>
            </div>
            <div class="modal-body p-4">
              <div class="mb-3">
                <label class="form-label small fw-bold">Nome do Caixa</label>
                <input v-model="newRegisterForm.name" type="text" class="form-control" placeholder="Ex: Guichê 2 - Peças" />
              </div>
              <div class="mb-3">
                <label class="form-label small fw-bold">Tipo</label>
                <select v-model="newRegisterForm.type" class="form-select">
                  <option value="user_station">Ponto de Atendimento / Guichê</option>
                  <option value="central">Caixa Central / Cofre</option>
                  <option value="mobile">Ponto Móvel / Pátio</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-bold">Teto de Gaveta para Alerta de Sangria (R$)</label>
                <input v-model.number="newRegisterForm.max_cash_limit" type="number" class="form-control" placeholder="2000" />
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-light rounded-pill px-3" @click="showNewRegisterModal = false">Cancelar</button>
              <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" @click="handleCreateRegister" :disabled="loading">
                Salvar Caixa
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
.letter-spacing-xs {
  letter-spacing: 0.05em;
}
.font-headline {
  font-family: 'Plus Jakarta Sans', sans-serif;
}
</style>

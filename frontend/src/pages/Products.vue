<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import Sidebar from '../components/Sidebar.vue';

const router = useRouter();
const authStore = useAuthStore();

// Estados da lista
const products = ref([]);
const categories = ref([]);
const pagination = ref({});
const search = ref('');
const filterCategory = ref('');
const filterStatus = ref(''); // low_stock, out_of_stock, active
const loading = ref(false);
const error = ref('');

// Estados do formulário
const showForm = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitLoading = ref(false);

const form = ref({
  category_id: '',
  name: '',
  sku: '',
  description: '',
  unit_price: 0,
  cost_price: 0,
  stock_quantity: 0,
  min_stock_level: 5,
  max_stock_level: 100,
  unit_of_measure: 'UN'
});

// Reset do formulário
const resetForm = () => {
  form.value = {
    category_id: categories.value[0]?.id || '',
    name: '',
    sku: '',
    description: '',
    unit_price: 0,
    cost_price: 0,
    stock_quantity: 0,
    min_stock_level: 5,
    max_stock_level: 100,
    unit_of_measure: 'UN'
  };
  isEditing.value = false;
  editingId.value = null;
};

// Carregar categorias de produtos da API
const fetchCategories = async () => {
  try {
    const response = await api.get('/product-categories');
    categories.value = Array.isArray(response.data) ? response.data : (response.data.data || []);
    if (categories.value.length > 0 && !form.value.category_id) {
      form.value.category_id = categories.value[0].id;
    }
  } catch (err) {
    console.error('Falha ao carregar categorias de produtos.');
  }
};

// Carregar produtos da API
const fetchProducts = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/products', {
      params: {
        search: search.value,
        category_id: filterCategory.value,
        status: filterStatus.value || 'active', // 'active' ou 'low_stock' / 'out_of_stock'
        page
      }
    });
    products.value = response.data.data || [];
    pagination.value = {
      current_page: response.data.current_page || 1,
      last_page: response.data.last_page || 1,
      prev_page_url: response.data.prev_page_url,
      next_page_url: response.data.next_page_url,
      total: response.data.total || 0
    };
  } catch (err) {
    error.value = 'Falha ao buscar produtos/estoque do servidor.';
  } finally {
    loading.value = false;
  }
};

// Submissão do formulário
const handleSubmit = async () => {
  submitLoading.value = true;
  error.value = '';
  try {
    const payload = { ...form.value };
    if (isEditing.value) {
      await api.put(`/products/${editingId.value}`, payload);
    } else {
      await api.post('/products', payload);
    }
    resetForm();
    showForm.value = false;
    fetchProducts();
  } catch (err) {
    error.value = err.response?.data?.message || err.response?.data?.error || 'Erro ao salvar produto. Verifique se o SKU é único.';
  } finally {
    submitLoading.value = false;
  }
};

// Editar produto
const handleEdit = (product) => {
  form.value = {
    category_id: product.category_id,
    name: product.name,
    sku: product.sku,
    description: product.description || '',
    unit_price: product.unit_price,
    cost_price: product.cost_price || 0,
    stock_quantity: product.stock_quantity || 0,
    min_stock_level: product.min_stock_level || 5,
    max_stock_level: product.max_stock_level || 100,
    unit_of_measure: product.unit_of_measure || 'UN'
  };
  isEditing.value = true;
  editingId.value = product.id;
  showForm.value = true;
};

// Excluir produto
const handleDelete = async (id) => {
  if (confirm('Tem certeza que deseja excluir/desativar este produto?')) {
    try {
      await api.delete(`/products/${id}`);
      fetchProducts();
    } catch (err) {
      alert('Erro ao excluir produto.');
    }
  }
};

// Monitoramento de filtros
watch([search, filterCategory, filterStatus], () => {
  fetchProducts(1);
});

// Inicialização
onMounted(async () => {
  await fetchCategories();
  await fetchProducts(1);
});

// Helper para formatar moeda
const formatCurrency = (val) => {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0);
};
</script>

<template>
  <div class="d-flex">
    <!-- Sidebar de Navegação Modular -->
    <Sidebar />

    <!-- Área de Conteúdo Principal -->
    <div class="flex-grow-1 min-vh-100 p-4" style="margin-left: 260px;">
      
      <!-- Cabeçalho Superior / Topbar -->
      <header class="d-flex justify-content-between align-items-center pb-4 mb-4 border-bottom">
        <div>
          <span class="text-muted small fw-bold text-uppercase tracking-wider">Módulos</span>
          <h1 class="h3 fw-bold text-slate-900 mb-0 font-headline">Controle de Estoque e Peças</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-slate-900 text-white px-3 py-2 fw-semibold">
            Oficina: {{ authStore.tenantName || 'Carregando...' }}
          </span>
        </div>
      </header>

      <!-- Alerta de Erro -->
      <div v-if="error" class="alert alert-danger alert-dismissible fade show rounded-lg shadow-sm" role="alert">
        {{ error }}
      </div>

      <!-- Barra de Ações e Filtros -->
      <div class="card border-0 shadow-xs mb-4 rounded-xl">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1" style="max-width: 900px;">
            <!-- Busca -->
            <div class="input-group flex-grow-1" style="min-width: 250px; max-width: 350px;">
              <span class="input-group-text bg-white border-end-0 text-muted">
                <i class="bi bi-search"></i>
              </span>
              <input
                v-model="search"
                type="text"
                class="form-control border-start-0 ps-0 rounded-end-lg"
                placeholder="Pesquisar por nome ou SKU..."
              />
            </div>
            
            <!-- Filtro de Categoria -->
            <select v-model="filterCategory" class="form-select rounded-lg" style="width: auto; min-width: 180px;">
              <option value="">Todas as categorias</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>

            <!-- Filtro de Estoque -->
            <select v-model="filterStatus" class="form-select rounded-lg" style="width: auto; min-width: 180px;">
              <option value="">Todos os status</option>
              <option value="active">Em Estoque</option>
              <option value="low_stock">Estoque Baixo</option>
              <option value="out_of_stock">Sem Estoque</option>
            </select>
          </div>

          <button
            @click="showForm = !showForm; if(!showForm) resetForm();"
            class="btn btn-primary fw-semibold rounded-lg d-flex align-items-center gap-2"
          >
            <i class="bi" :class="showForm ? 'bi-x-lg' : 'bi-plus-lg'"></i>
            {{ showForm ? 'Fechar' : 'Nova Peça/Produto' }}
          </button>
        </div>
      </div>

      <!-- Formulário de Cadastro / Edição -->
      <div v-if="showForm" class="card border-0 shadow-xs mb-4 rounded-xl border-top-slate">
        <div class="card-header bg-white border-0 pt-4 px-4">
          <h5 class="fw-bold text-slate-900 mb-0 font-headline">
            {{ isEditing ? 'Editar Peça/Produto' : 'Cadastrar Peça de Reposição / Produto' }}
          </h5>
        </div>
        <div class="card-body px-4 pb-4">
          <!-- Alerta se não houver categorias -->
          <div v-if="categories.length === 0" class="alert alert-warning rounded-lg mb-3">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Nenhuma categoria de produto cadastrada no banco. Cadastre uma categoria de produtos antes de prosseguir.
          </div>

          <form @submit.prevent="handleSubmit">
            <div class="row">
              <!-- Nome -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Nome do Produto / Peça</label>
                <input v-model="form.name" type="text" class="form-control rounded-lg" required placeholder="Ex: Filtro de Óleo Scania, Pastilha de Freio Traseira" />
              </div>
              <!-- SKU -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Código SKU (Referência)</label>
                <input v-model="form.sku" type="text" class="form-control rounded-lg" required placeholder="Ex: SKU-987654" />
              </div>
              <!-- Categoria -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Categoria</label>
                <select v-model="form.category_id" class="form-select rounded-lg" required>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </option>
                </select>
              </div>

              <!-- Preço de Custo -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Preço de Custo (R$)</label>
                <input v-model="form.cost_price" type="number" step="0.01" class="form-control rounded-lg" required />
              </div>
              <!-- Preço de Venda -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Preço de Venda (R$)</label>
                <input v-model="form.unit_price" type="number" step="0.01" class="form-control rounded-lg" required />
              </div>
              <!-- Quantidade inicial -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Qtd. em Estoque</label>
                <input v-model="form.stock_quantity" type="number" class="form-control rounded-lg" required :disabled="isEditing" />
              </div>
              <!-- Unidade de medida -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Unidade Medida</label>
                <select v-model="form.unit_of_measure" class="form-select rounded-lg">
                  <option value="UN">Unidade (UN)</option>
                  <option value="PC">Peça (PC)</option>
                  <option value="L">Litro (L)</option>
                  <option value="KG">Quilo (KG)</option>
                  <option value="JG">Jogo (JG)</option>
                </select>
              </div>

              <!-- Nível de Estoque Mínimo -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Estoque Mínimo (Alerta de Reposição)</label>
                <input v-model="form.min_stock_level" type="number" class="form-control rounded-lg" required />
              </div>
              <!-- Nível de Estoque Máximo -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Estoque Máximo Planejado</label>
                <input v-model="form.max_stock_level" type="number" class="form-control rounded-lg" required />
              </div>

              <!-- Descrição -->
              <div class="col-12 mb-4">
                <label class="form-label small fw-semibold text-slate-700">Descrição Comercial / Detalhes</label>
                <textarea v-model="form.description" class="form-control rounded-lg" rows="3" placeholder="Informações de compatibilidade do fabricante, fornecedor, etc..."></textarea>
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
              <button type="button" @click="showForm = false; resetForm();" class="btn btn-light fw-semibold rounded-lg">
                Cancelar
              </button>
              <button type="submit" class="btn btn-primary fw-semibold rounded-lg" :disabled="submitLoading || categories.length === 0">
                <span v-if="submitLoading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                {{ isEditing ? 'Atualizar Peça' : 'Salvar Peça' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Tabela de Produtos -->
      <div class="card border-0 shadow-xs rounded-xl overflow-hidden">
        <div class="card-body p-0">
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2 small">Carregando inventário de peças...</p>
          </div>

          <div v-else-if="products.length === 0" class="text-center py-5">
            <p class="text-muted mb-0">Nenhuma peça cadastrada ou correspondente aos filtros.</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="px-4 text-slate-700 fw-bold border-bottom-0">Peça / Referência</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Categoria</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Descrição</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Preço Venda</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Qtd. Estoque</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Alerta</th>
                  <th class="text-end px-4 text-slate-700 fw-bold border-bottom-0">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="prod in products" :key="prod.id" class="table-row-compact">
                  <td class="px-4">
                    <div class="fw-semibold text-slate-900">{{ prod.name }}</div>
                    <small class="text-muted text-uppercase font-monospace" style="font-size: 0.65rem;">
                      {{ prod.sku }}
                    </small>
                  </td>
                  <td>
                    <span class="badge bg-secondary-soft text-secondary font-monospace" style="font-size: 0.65rem;">
                      {{ prod.category?.name || 'Geral' }}
                    </span>
                  </td>
                  <td class="small text-slate-700 text-truncate" style="max-width: 250px;" :title="prod.description">
                    {{ prod.description || 'Sem descrição' }}
                  </td>
                  <td class="fw-semibold text-slate-900 font-monospace">
                    {{ formatCurrency(prod.unit_price) }}
                  </td>
                  <td class="font-monospace fw-bold text-slate-900">
                    {{ prod.stock_quantity }} <small class="text-muted fw-normal">{{ prod.unit_of_measure }}</small>
                  </td>
                  <td>
                    <!-- Alerta de Estoque Mínimo (Estilo Stitch) -->
                    <span
                      v-if="prod.stock_quantity === 0"
                      class="badge bg-danger-soft text-danger"
                      style="font-size: 0.65rem;"
                    >
                      Sem Estoque
                    </span>
                    <span
                      v-else-if="prod.stock_quantity <= prod.min_stock_level"
                      class="badge bg-warning-soft text-warning"
                      style="font-size: 0.65rem;"
                    >
                      Estoque Baixo
                    </span>
                    <span
                      v-else
                      class="badge bg-success-soft text-success"
                      style="font-size: 0.65rem;"
                    >
                      Seguro
                    </span>
                  </td>
                  <td class="text-end px-4">
                    <button @click="handleEdit(prod)" class="btn btn-outline-primary btn-sm me-2 fw-semibold rounded-lg py-1 px-2.5" style="font-size: 0.75rem;">
                      Editar
                    </button>
                    <button @click="handleDelete(prod.id)" class="btn btn-outline-danger btn-sm fw-semibold rounded-lg py-1 px-2.5" style="font-size: 0.75rem;">
                      Excluir
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Rodapé de Paginação -->
        <div v-if="pagination.last_page > 1" class="card-footer bg-white border-0 d-flex justify-content-between align-items-center py-3 px-4 border-top">
          <span class="text-muted small">Total de {{ pagination.total }} itens no inventário</span>
          <div class="d-flex gap-1">
            <button
              @click="fetchProducts(pagination.current_page - 1)"
              class="btn btn-outline-secondary btn-sm rounded-lg"
              :disabled="pagination.current_page === 1"
            >
              Anterior
            </button>
            <button
              @click="fetchProducts(pagination.current_page + 1)"
              class="btn btn-outline-secondary btn-sm rounded-lg"
              :disabled="pagination.current_page === pagination.last_page"
            >
              Próximo
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.text-slate-900 {
  color: #0f172a;
}
.text-slate-700 {
  color: #334155;
}
.bg-slate-900 {
  background-color: #0f172a;
}
.border-top-slate {
  border-top: 3px solid #0f172a;
}
.rounded-xl {
  border-radius: 12px;
}
.rounded-lg {
  border-radius: 8px;
}
.bg-primary-soft {
  background-color: rgba(13, 110, 253, 0.1);
}
.bg-secondary-soft {
  background-color: rgba(108, 117, 125, 0.1);
}
.bg-success-soft {
  background-color: rgba(40, 167, 69, 0.1);
}
.bg-warning-soft {
  background-color: rgba(255, 193, 7, 0.1);
}
.bg-danger-soft {
  background-color: rgba(220, 53, 69, 0.1);
}
.table th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.7rem;
  letter-spacing: 0.5px;
  border-bottom: none;
}
.table-row-compact {
  height: 48px;
}
.form-control:focus, .form-select:focus {
  border-color: #0f172a;
  box-shadow: 0 0 0 0.2rem rgba(15, 23, 42, 0.15);
}
</style>

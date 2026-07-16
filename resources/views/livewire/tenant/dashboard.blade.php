<div class="dashboard-wrapper" style="font-family: 'Outfit', sans-serif; background: #0f172a; color: #f1f5f9; padding: 2rem; min-height: 100vh;">
    <!-- Dashboard Header -->
    <div class="dashboard-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #334155; padding-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 2.25rem; font-weight: 700; margin: 0; background: linear-gradient(135deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                Painel Analítico de Controle
            </h1>
            <p style="color: #94a3b8; margin: 0.25rem 0 0 0; font-size: 0.875rem;">Visão gerencial e financeira consolidada do ERP SaaS</p>
        </div>
        
        <!-- Filter Controls -->
        <div class="filter-controls" style="display: flex; gap: 1rem; align-items: center; background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(10px); padding: 0.75rem 1.25rem; border-radius: 12px; border: 1px solid #1e293b;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label for="startDate" style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">DE:</label>
                <input type="date" id="startDate" wire:model.live="startDate" style="background: #0f172a; border: 1px solid #334155; color: #f1f5f9; border-radius: 6px; padding: 0.25rem 0.5rem; font-size: 0.875rem; outline: none;">
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label for="endDate" style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">ATÉ:</label>
                <input type="date" id="endDate" wire:model.live="endDate" style="background: #0f172a; border: 1px solid #334155; color: #f1f5f9; border-radius: 6px; padding: 0.25rem 0.5rem; font-size: 0.875rem; outline: none;">
            </div>
        </div>
    </div>

    <!-- Main Statistics Grid -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
        <!-- Card: Total Billed -->
        <div class="stat-card" style="background: linear-gradient(145deg, #1e293b, #0f172a); border: 1px solid #334155; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25); position: relative; overflow: hidden; transition: transform 0.2s;">
            <div style="position: absolute; top: -10px; right: -10px; width: 60px; height: 60px; background: rgba(56, 189, 248, 0.05); border-radius: 50%;"></div>
            <p style="color: #94a3b8; margin: 0; font-size: 0.875rem; font-weight: 600;">Total Faturado</p>
            <h3 style="font-size: 1.875rem; font-weight: 700; margin: 0.5rem 0; color: #38bdf8;">R$ {{ number_format($revenueReport['total_billed'], 2, ',', '.') }}</h3>
            <p style="color: #10b981; margin: 0; font-size: 0.75rem; font-weight: 600;">Impostos (ISS 5%): R$ {{ number_format($revenueReport['total_taxes'], 2, ',', '.') }}</p>
        </div>

        <!-- Card: Total Paid -->
        <div class="stat-card" style="background: linear-gradient(145deg, #1e293b, #0f172a); border: 1px solid #334155; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25); position: relative; overflow: hidden; transition: transform 0.2s;">
            <div style="position: absolute; top: -10px; right: -10px; width: 60px; height: 60px; background: rgba(16, 185, 129, 0.05); border-radius: 50%;"></div>
            <p style="color: #94a3b8; margin: 0; font-size: 0.875rem; font-weight: 600;">Total Recebido</p>
            <h3 style="font-size: 1.875rem; font-weight: 700; margin: 0.5rem 0; color: #10b981;">R$ {{ number_format($revenueReport['total_paid'], 2, ',', '.') }}</h3>
            <p style="color: #f59e0b; margin: 0; font-size: 0.75rem; font-weight: 600;">Pendente: R$ {{ number_format($revenueReport['total_pending'], 2, ',', '.') }}</p>
        </div>

        <!-- Card: Net Profit -->
        <div class="stat-card" style="background: linear-gradient(145deg, #1e293b, #0f172a); border: 1px solid #334155; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25); position: relative; overflow: hidden; transition: transform 0.2s;">
            <div style="position: absolute; top: -10px; right: -10px; width: 60px; height: 60px; background: rgba(129, 140, 248, 0.05); border-radius: 50%;"></div>
            <p style="color: #94a3b8; margin: 0; font-size: 0.875rem; font-weight: 600;">Lucro Líquido (OS)</p>
            <h3 style="font-size: 1.875rem; font-weight: 700; margin: 0.5rem 0; color: #818cf8;">R$ {{ number_format($revenueReport['net_profit'], 2, ',', '.') }}</h3>
            <p style="color: #38bdf8; margin: 0; font-size: 0.75rem; font-weight: 600;">Margem Média: {{ $revenueReport['profit_margin_percentage'] }}%</p>
        </div>

        <!-- Card: Active Orders -->
        <div class="stat-card" style="background: linear-gradient(145deg, #1e293b, #0f172a); border: 1px solid #334155; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25); position: relative; overflow: hidden; transition: transform 0.2s;">
            <div style="position: absolute; top: -10px; right: -10px; width: 60px; height: 60px; background: rgba(245, 158, 11, 0.05); border-radius: 50%;"></div>
            <p style="color: #94a3b8; margin: 0; font-size: 0.875rem; font-weight: 600;">Total de Ordens de Serviço</p>
            <h3 style="font-size: 1.875rem; font-weight: 700; margin: 0.5rem 0; color: #fb7185;">{{ $orderStats['total_orders'] }} OS</h3>
            <p style="color: #cbd5e1; margin: 0; font-size: 0.75rem; font-weight: 600;">Tempo Médio: {{ $orderStats['average_turnaround_hours'] }} Horas</p>
        </div>
    </div>

    <!-- Secondary Grid: Inventory & Driver CNH Alerts -->
    <div class="secondary-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2.5rem; @media (max-width: 1024px) { grid-template-columns: 1fr; }">
        <!-- Stock valuation and alerts -->
        <div class="inventory-summary" style="background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 2rem;">
            <h2 style="font-size: 1.25rem; font-weight: 700; margin-top: 0; margin-bottom: 1.5rem; color: #f1f5f9; display: flex; justify-content: space-between;">
                <span>Valoração de Estoque</span>
                <span style="font-size: 0.75rem; color: #94a3b8; background: #0f172a; padding: 0.25rem 0.5rem; border-radius: 4px;">{{ $inventoryReport['total_unique_products'] }} Produtos</span>
            </h2>
            <div style="display: flex; justify-content: space-around; margin-bottom: 2rem; border-bottom: 1px solid #334155; padding-bottom: 1.5rem;">
                <div style="text-align: center;">
                    <p style="margin: 0; font-size: 0.75rem; color: #94a3b8;">Estoque a Custo</p>
                    <p style="margin: 0.25rem 0 0 0; font-size: 1.25rem; font-weight: 700; color: #cbd5e1;">R$ {{ number_format($inventoryReport['valuation_cost'], 2, ',', '.') }}</p>
                </div>
                <div style="text-align: center;">
                    <p style="margin: 0; font-size: 0.75rem; color: #94a3b8;">Estoque a Venda</p>
                    <p style="margin: 0.25rem 0 0 0; font-size: 1.25rem; font-weight: 700; color: #38bdf8;">R$ {{ number_format($inventoryReport['valuation_retail'], 2, ',', '.') }}</p>
                </div>
                <div style="text-align: center;">
                    <p style="margin: 0; font-size: 0.75rem; color: #94a3b8;">Margem Esperada</p>
                    <p style="margin: 0.25rem 0 0 0; font-size: 1.25rem; font-weight: 700; color: #10b981;">{{ $inventoryReport['projected_margin_percentage'] }}%</p>
                </div>
            </div>

            <!-- Stock Alerts list -->
            <h3 style="font-size: 0.875rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.75rem; letter-spacing: 0.05em;">Alertas de Estoque Baixo</h3>
            @if(empty($inventoryReport['low_stock_alerts']))
                <p style="color: #64748b; font-size: 0.875rem; margin: 0;">Nenhum alerta de estoque baixo.</p>
            @else
                <div class="scroll-container" style="max-height: 180px; overflow-y: auto; padding-right: 0.5rem;">
                    @foreach($inventoryReport['low_stock_alerts'] as $alert)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #334155;">
                            <div>
                                <p style="margin: 0; font-size: 0.875rem; font-weight: 600; color: #e2e8f0;">{{ $alert['name'] }}</p>
                                <p style="margin: 0; font-size: 0.75rem; color: #64748b;">SKU: {{ $alert['sku'] }} | Loc: {{ $alert['location'] }}</p>
                            </div>
                            <span style="font-size: 0.75rem; font-weight: 700; color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 0.25rem 0.5rem; border-radius: 4px;">
                                Estoque: {{ $alert['stock'] }} (Mín: {{ $alert['min_stock'] }})
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Drivers Performance & CNH Expirations -->
        <div class="driver-summary" style="background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 2rem;">
            <h2 style="font-size: 1.25rem; font-weight: 700; margin-top: 0; margin-bottom: 1.5rem; color: #f1f5f9; display: flex; justify-content: space-between;">
                <span>Estatísticas de Motoristas</span>
                <span style="font-size: 0.75rem; color: #94a3b8; background: #0f172a; padding: 0.25rem 0.5rem; border-radius: 4px;">{{ $driverPerformance['total_drivers'] }} Ativos</span>
            </h2>

            <div style="display: flex; justify-content: space-around; margin-bottom: 1.5rem; background: #0f172a; padding: 1rem; border-radius: 8px;">
                <div style="text-align: center;">
                    <p style="margin: 0; font-size: 0.875rem; font-weight: 700; color: #10b981;">{{ $driverPerformance['cnh_status']['valid_count'] }}</p>
                    <p style="margin: 0; font-size: 0.75rem; color: #94a3b8;">Habilitados</p>
                </div>
                <div style="text-align: center;">
                    <p style="margin: 0; font-size: 0.875rem; font-weight: 700; color: #f59e0b;">{{ $driverPerformance['cnh_status']['expiring_count'] }}</p>
                    <p style="margin: 0; font-size: 0.75rem; color: #94a3b8;">A Vencer (30d)</p>
                </div>
                <div style="text-align: center;">
                    <p style="margin: 0; font-size: 0.875rem; font-weight: 700; color: #ef4444;">{{ $driverPerformance['cnh_status']['expired_count'] }}</p>
                    <p style="margin: 0; font-size: 0.75rem; color: #94a3b8;">Vencidas</p>
                </div>
            </div>

            <!-- CNH Warnings list -->
            <h3 style="font-size: 0.875rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.75rem; letter-spacing: 0.05em;">Alertas de Vencimento de CNH</h3>
            @if(empty($driverPerformance['expired_alerts']) && empty($driverPerformance['expiring_alerts']))
                <p style="color: #64748b; font-size: 0.875rem; margin: 0;">Nenhum alerta de CNH ativa.</p>
            @else
                <div class="scroll-container" style="max-height: 180px; overflow-y: auto; padding-right: 0.5rem;">
                    <!-- Expired CNHs -->
                    @foreach($driverPerformance['expired_alerts'] as $driver)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #334155;">
                            <div>
                                <p style="margin: 0; font-size: 0.875rem; font-weight: 600; color: #ef4444;">{{ $driver['name'] }}</p>
                                <p style="margin: 0; font-size: 0.75rem; color: #64748b;">CNH: {{ $driver['cnh'] }} | Venceu: {{ $driver['expiration_date'] }}</p>
                            </div>
                            <span style="font-size: 0.75rem; font-weight: 700; color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 0.25rem 0.5rem; border-radius: 4px;">
                                Vencida há {{ $driver['days_expired'] }} dias
                            </span>
                        </div>
                    @endforeach

                    <!-- Expiring CNHs -->
                    @foreach($driverPerformance['expiring_alerts'] as $driver)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #334155;">
                            <div>
                                <p style="margin: 0; font-size: 0.875rem; font-weight: 600; color: #f59e0b;">{{ $driver['name'] }}</p>
                                <p style="margin: 0; font-size: 0.75rem; color: #64748b;">CNH: {{ $driver['cnh'] }} | Vence em: {{ $driver['expiration_date'] }}</p>
                            </div>
                            <span style="font-size: 0.75rem; font-weight: 700; color: #f59e0b; background: rgba(245, 158, 11, 0.1); padding: 0.25rem 0.5rem; border-radius: 4px;">
                                Restam {{ $driver['days_remaining'] }} dias
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

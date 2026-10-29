"""Builds artificial-intelligence.json: the AI faculty's master's program page.

Source: the faculty's curriculum for the Professional Master's Degree in
Artificial Intelligence & Data Sciences (MADS). Sections the source gives in one
language only (admission, project rules, degree requirements, the CLOs) were
translated. MADS405 uses the overall table's hours (4 theory, 8 practical, 8
credits); term totals are computed by the site, not copied from the source.

Run: python3 build_artificial_intelligence.py  (writes the JSON next to it)
"""

import json
from pathlib import Path

GRADING = {
    "en": "Final exam 50%, projects/assignments 35%, quizzes and participation 15%.",
    "ar": "الامتحان النهائي 50%، مشاريع/واجبات 35%، اختبارات ومشاركة 15%.",
}

TERMS = [
    {
        "id": "term-1",
        "label": {"en": "Year 1 · Semester 1", "ar": "السنة الأولى · الفصل الأول"},
        "theme": {"en": "Foundations", "ar": "الأسس"},
    },
    {
        "id": "term-2",
        "label": {"en": "Year 1 · Semester 2", "ar": "السنة الأولى · الفصل الثاني"},
        "theme": {"en": "Core AI & Data Engineering", "ar": "الذكاء الاصطناعي الأساسي وهندسة البيانات"},
    },
    {
        "id": "term-3",
        "label": {"en": "Year 2 · Semester 3", "ar": "السنة الثانية · الفصل الثالث"},
        "theme": {"en": "Advanced AI & Specializations", "ar": "الذكاء الاصطناعي المتقدم والتخصصات"},
    },
    {
        "id": "term-4",
        "label": {"en": "Year 2 · Semester 4", "ar": "السنة الثانية · الفصل الرابع"},
        "theme": {"en": "Research & Graduation Project", "ar": "البحث ومشروع التخرج"},
    },
]

# code, term, theory, practical, credits, prerequisites, en title, ar title,
# en description, ar description, en CLOs, ar CLOs, coverage (PLO -> I/R/M)
COURSES = [
    dict(
        code="MADS101", term="term-1", theory=3, practical=2, credits=4, prerequisites=[],
        title_en="Advanced Python Programming for AI",
        title_ar="البرمجة المتقدمة بلغة بايثون للذكاء الاصطناعي",
        desc_en="Covers Python 3 advanced features, OOP, functional programming, asynchronous programming, NumPy, Pandas, Matplotlib, and best practices for scientific computing. Emphasizes writing efficient, testable, and production-ready AI code.",
        desc_ar="يغطي الميزات المتقدمة في Python 3 والبرمجة غرضية التوجّه، والبرمجة الوظيفية والبرمجة غير المتزامنة، و NumPy، و Pandas، و Matplotlib، وأفضل الممارسات في الحوسبة العلمية. كما يركّز على كتابة كود ذكاء اصطناعي فعّال وقابل للاختبار وجاهز للنشر.",
        clo_en=[
            "Write and debug advanced Python scripts using OOP and functional paradigms.",
            "Manipulate and visualize large datasets using NumPy, Pandas, and Matplotlib.",
            "Develop asynchronous and parallel Python applications for data pipelines.",
            "Apply software engineering best practices to AI project development.",
        ],
        clo_ar=[
            "كتابة نصوص Python متقدمة وتصحيحها باستخدام البرمجة غرضية التوجّه والبرمجة الوظيفية.",
            "معالجة مجموعات البيانات الكبيرة وتمثيلها بصرياً باستخدام NumPy و Pandas و Matplotlib.",
            "تطوير تطبيقات Python غير متزامنة ومتوازية لمسارات البيانات.",
            "تطبيق أفضل ممارسات هندسة البرمجيات في تطوير مشاريع الذكاء الاصطناعي.",
        ],
        coverage={"PLO1": "I", "PLO7": "I", "PLO9": "I"},
    ),
    dict(
        code="MADS102", term="term-1", theory=3, practical=2, credits=4, prerequisites=[],
        title_en="Advanced Algorithms & Data Structures for AI",
        title_ar="الخوارزميات وبنى المعطيات المتقدمة للذكاء الاصطناعي",
        desc_en="Advanced algorithm design paradigms with a focus on AI-specific algorithms: A* and heuristic search, minimax and alpha-beta pruning, Monte Carlo Tree Search, constraint satisfaction, genetic algorithms, simulated annealing, ant colony and swarm intelligence, approximation algorithms, and NP-hard problem handling. Data structures critical for AI: priority queues, tries, graphs, KD-trees, and spatial indexing.",
        desc_ar="نماذج تصميم الخوارزميات المتقدمة مع التركيز على الخوارزميات الخاصة بالذكاء الاصطناعي: A*، والبحث الإرشادي، وقطع ألفا-بيتا و minimax، البحث في شجرة مونت كارلو، وإشباع القيود، والخوارزميات الجينية، والتلدد المحاكى، وذكاء النمل والأسراب، والخوارزميات التقريبية، ومعالجة المسائل من نمط NP-hard، وبنى المعطيات للذكاء الاصطناعي: طوابير الأولوية، والأشجار، والبيان، وأشجار KD، والفهرسة المكانية.",
        clo_en=[
            "Implement and compare heuristic search algorithms including A* and its variants.",
            "Apply adversarial search and game-tree algorithms to AI decision problems.",
            "Design and evaluate metaheuristic and evolutionary algorithms for optimization.",
            "Select and implement advanced AI-relevant data structures for efficient computation.",
        ],
        clo_ar=[
            "تنفيذ خوارزميات البحث الإرشادي ومقارنتها، بما فيها A* وأشكالها.",
            "تطبيق خوارزميات البحث التنافسي وأشجار الألعاب على مسائل القرار في الذكاء الاصطناعي.",
            "تصميم الخوارزميات الميتا-إرشادية والتطورية للتحسين وتقييمها.",
            "اختيار بنى المعطيات المتقدمة الملائمة للذكاء الاصطناعي وتنفيذها لحوسبة فعّالة.",
        ],
        coverage={"PLO1": "I", "PLO2": "I", "PLO7": "I"},
    ),
    dict(
        code="MADS103", term="term-1", theory=3, practical=2, credits=4, prerequisites=[],
        title_en="Mathematical Foundations for AI",
        title_ar="الأسس الرياضية للذكاء الاصطناعي",
        desc_en="A unified course integrating probability theory, statistics, and numerical methods essential for AI. Topics include: random variables, distributions, Bayesian inference, hypothesis testing, regression analysis, multivariate statistics, linear algebra for ML, numerical methods for solving ODEs/PDEs, root-finding, interpolation, numerical integration, gradient-based and convex optimization, and metaheuristic optimization techniques.",
        desc_ar="مقرر موحَّد يدمج نظرية الاحتمالات والإحصاء والأساليب العددية الأساسية للذكاء الاصطناعي. تشمل الموضوعات: المتغيرات العشوائية، والتوزيعات والاستدلال البايزي، واختبار الفرضيات، وتحليل الانحدار، والإحصاء متعدد المتغيرات، والجبر الخطي للتعلم الآلي، والأساليب العددية لحل المعادلات التفاضلية، وإيجاد الجذور، والاستيفاء، والتكامل العددي، والتحسين المبني على التدرج والمحدب، وتقنيات التحسين الميتا-إرشادي.",
        clo_en=[
            "Apply probability distributions and Bayesian inference to model AI problems.",
            "Perform statistical analysis and hypothesis testing relevant to data science.",
            "Apply linear algebra and numerical methods to AI computations.",
            "Implement and compare gradient-based and metaheuristic optimization algorithms.",
        ],
        clo_ar=[
            "تطبيق التوزيعات الاحتمالية والاستدلال البايزي لنمذجة مسائل الذكاء الاصطناعي.",
            "إجراء التحليل الإحصائي واختبار الفرضيات في سياق علوم البيانات.",
            "تطبيق الجبر الخطي والأساليب العددية في حسابات الذكاء الاصطناعي.",
            "تنفيذ خوارزميات التحسين المبنية على التدرج والميتا-إرشادية ومقارنتها.",
        ],
        coverage={"PLO1": "I", "PLO2": "I", "PLO6": "I"},
    ),
    dict(
        code="MADS104", term="term-1", theory=3, practical=2, credits=4, prerequisites=[],
        title_en="Advanced Databases for AI",
        title_ar="قواعد البيانات المتقدمة للذكاء الاصطناعي",
        desc_en="Relational database design, SQL optimization, data warehousing, OLAP, introduction to NewSQL systems, and database architectures for AI workloads including in-memory databases and time-series databases.",
        desc_ar="تصميم قواعد البيانات العلائقية، وتحسين SQL، ومستودعات البيانات، و OLAP، ومقدمة إلى أنظمة NewSQL، ومعماريات قواعد البيانات لأحمال عمل الذكاء الاصطناعي بما فيها قواعد البيانات في الذاكرة والسلاسل الزمنية.",
        clo_en=[
            "Design and optimize relational schemas for analytical workloads.",
            "Implement data warehousing and OLAP solutions.",
            "Compare and select database technologies for various AI use cases.",
            "Apply SQL and query optimization techniques for large datasets.",
        ],
        clo_ar=[
            "تصميم المخططات العلائقية وتحسينها لأحمال العمل التحليلية.",
            "تنفيذ حلول مستودعات البيانات و OLAP.",
            "مقارنة تقنيات قواعد البيانات واختيار الملائم منها لحالات استخدام الذكاء الاصطناعي المختلفة.",
            "تطبيق تقنيات SQL وتحسين الاستعلامات على مجموعات البيانات الكبيرة.",
        ],
        coverage={"PLO1": "I", "PLO4": "I", "PLO7": "I"},
    ),
    dict(
        code="MADS105", term="term-1", theory=3, practical=2, credits=4, prerequisites=[],
        title_en="Distributed Systems & Cloud Computing",
        title_ar="الأنظمة الموزعة والحوسبة السحابية",
        desc_en="Principles of distributed computing, consistency models, fault tolerance, microservices, containerization (Docker, Kubernetes), cloud platforms (AWS, GCP, Azure), serverless computing, and cloud-native AI deployment.",
        desc_ar="مبادئ الحوسبة الموزعة، ونماذج التناسق، والتسامح مع الأعطال، والخدمات المصغرة، والحاويات (Docker وKubernetes)، ومنصات السحابة (AWS وGCP وAzure)، والحوسبة بلا خادم، ونشر الذكاء الاصطناعي السحابي الأصيل.",
        clo_en=[
            "Explain distributed systems concepts including consistency and fault tolerance.",
            "Deploy containerized AI applications using Docker and Kubernetes.",
            "Design cloud-native architectures for AI workloads on major platforms.",
            "Implement scalable data pipelines using cloud services.",
        ],
        clo_ar=[
            "شرح مفاهيم الأنظمة الموزعة بما فيها التناسق والتسامح مع الأعطال.",
            "نشر تطبيقات ذكاء اصطناعي ضمن حاويات باستخدام Docker و Kubernetes.",
            "تصميم معماريات سحابية أصيلة لأحمال عمل الذكاء الاصطناعي على المنصات الرئيسية.",
            "تنفيذ مسارات بيانات قابلة للتوسع باستخدام الخدمات السحابية.",
        ],
        coverage={"PLO4": "I", "PLO6": "I", "PLO7": "I"},
    ),
    dict(
        code="MADS106", term="term-1", theory=3, practical=2, credits=4, prerequisites=[],
        title_en="Natural Language Processing",
        title_ar="معالجة اللغات الطبيعية",
        desc_en="Text preprocessing, tokenization, word embeddings (Word2Vec, GloVe, FastText), sequence models, BERT, GPT architectures, named entity recognition, sentiment analysis, machine translation, and text summarization.",
        desc_ar="المعالجة المسبقة للنص، والترميز، والتضمينات الكلمية (Word2Vec, GloVe, FastText)، ونماذج السلسلة، ومعماريات BERT و GPT، والتعرف على الكيانات المسماة، وتحليل المشاعر، والترجمة الآلية، وتلخيص النصوص.",
        clo_en=[
            "Implement text preprocessing and feature extraction pipelines.",
            "Apply pre-trained language models for downstream NLP tasks.",
            "Fine-tune transformer models for specific NLP applications.",
            "Evaluate NLP systems using appropriate metrics.",
        ],
        clo_ar=[
            "تنفيذ مسارات المعالجة المسبقة للنصوص واستخراج الميزات.",
            "تطبيق نماذج اللغة المدرّبة مسبقاً على مهام معالجة اللغة اللاحقة.",
            "الضبط الدقيق لنماذج المحوّلات لتطبيقات محددة في معالجة اللغة.",
            "تقييم أنظمة معالجة اللغات الطبيعية باستخدام المقاييس المناسبة.",
        ],
        coverage={"PLO1": "I", "PLO5": "I", "PLO7": "I"},
    ),
    dict(
        code="MADS201", term="term-2", theory=3, practical=2, credits=4, prerequisites=["MADS101", "MADS103"],
        title_en="Machine Learning",
        title_ar="تعلم الآلة",
        desc_en="Supervised and unsupervised learning, regression, classification, clustering, dimensionality reduction, ensemble methods, model evaluation, cross-validation, bias-variance tradeoff, and feature engineering.",
        desc_ar="التعلم الخاضع وغير الخاضع للإشراف، والانحدار، والتصنيف، والتجميع، وتقليل الأبعاد، وأساليب التجميع، وتقييم النماذج، والتحقق المتقاطع، ومقايضة التحيز والتباين، وهندسة الميزات.",
        clo_en=[
            "Implement and evaluate supervised learning algorithms.",
            "Apply unsupervised techniques for clustering and dimensionality reduction.",
            "Design feature engineering pipelines for diverse data types.",
            "Evaluate and tune ML models using rigorous validation techniques.",
        ],
        clo_ar=[
            "تنفيذ خوارزميات التعلم الخاضع للإشراف وتقييمها.",
            "تطبيق تقنيات التعلم غير الخاضع للإشراف في التجميع وتقليل الأبعاد.",
            "تصميم مسارات هندسة الميزات لأنواع البيانات المختلفة.",
            "تقييم نماذج تعلم الآلة وضبطها باستخدام تقنيات تحقق صارمة.",
        ],
        coverage={"PLO1": "R", "PLO2": "I", "PLO7": "R"},
    ),
    dict(
        code="MADS202", term="term-2", theory=3, practical=2, credits=4, prerequisites=["MADS101", "MADS103"],
        title_en="Deep Learning",
        title_ar="التعلم العميق",
        desc_en="Neural network fundamentals, backpropagation, CNNs, RNNs, LSTMs, Transformers, attention mechanisms, transfer learning, regularization, advanced optimization, and deployment with TensorFlow and PyTorch.",
        desc_ar="أسس الشبكات العصبية، والانتشار العكسي، والشبكات العصبية التلافيفية والشبكات العصبية المتكررة، وLSTMs، والمحوّلات، وآليات الانتباه، والتعلم بالنقل، والتنظيم، والتحسين المتقدم، والنشر باستخدام TensorFlow وPyTorch.",
        clo_en=[
            "Build and train CNNs and RNNs for vision and sequence tasks.",
            "Implement Transformer architectures from scratch.",
            "Apply transfer learning and fine-tuning strategies.",
            "Deploy deep learning models using TensorFlow and PyTorch.",
        ],
        clo_ar=[
            "بناء الشبكات التلافيفية والمتكررة وتدريبها لمهام الرؤية والسلاسل.",
            "تنفيذ معماريات المحوّلات من الصفر.",
            "تطبيق استراتيجيات التعلم بالنقل والضبط الدقيق.",
            "نشر نماذج التعلم العميق باستخدام TensorFlow و PyTorch.",
        ],
        coverage={"PLO1": "R", "PLO2": "R", "PLO7": "R"},
    ),
    dict(
        code="MADS203", term="term-2", theory=3, practical=2, credits=4, prerequisites=["MADS104"],
        title_en="NoSQL & Big Data Technologies",
        title_ar="تقنيات NoSQL والبيانات الكبيرة",
        desc_en="NoSQL paradigms (document, key-value, column-family, graph), MongoDB, Cassandra, Redis, Neo4j, Apache Hadoop, HDFS, MapReduce, Apache Spark, Kafka, and data lake architectures for AI workloads.",
        desc_ar="نماذج NoSQL (المستند، القيمة المفتاحية، عائلة الأعمدة، الرسم البياني) و MongoDB, Cassandra, Redis, Neo4j, Apache Hadoop, HDFS, MapReduce, Apache Spark, Kafka ومعماريات بحيرات البيانات لأحمال عمل الذكاء الاصطناعي.",
        clo_en=[
            "Design and implement NoSQL database solutions for AI applications.",
            "Build and operate Apache Spark pipelines for large-scale data processing.",
            "Design streaming data architectures using Kafka.",
            "Implement data lake solutions and govern data quality.",
        ],
        clo_ar=[
            "تصميم حلول قواعد بيانات NoSQL وتنفيذها لتطبيقات الذكاء الاصطناعي.",
            "بناء مسارات Apache Spark وتشغيلها لمعالجة البيانات على نطاق واسع.",
            "تصميم معماريات البيانات المتدفقة باستخدام Kafka.",
            "تنفيذ حلول بحيرات البيانات وحوكمة جودة البيانات.",
        ],
        coverage={"PLO4": "R", "PLO7": "R"},
    ),
    dict(
        code="MADS204", term="term-2", theory=3, practical=2, credits=4, prerequisites=["MADS101", "MADS102", "MADS103"],
        title_en="Image Processing & Computer Vision",
        title_ar="معالجة الصور والرؤية الحاسوبية",
        desc_en="Image fundamentals, filtering, morphological operations, feature extraction (SIFT, HOG), object detection (YOLO, SSD, Faster R-CNN), image segmentation, pose estimation, 3D vision, and OpenCV with deep learning frameworks.",
        desc_ar="أسس الصورة، والتصفية، والعمليات المورفولوجية، واستخراج الميزات (SIFT, HOG)، والكشف عن العناصر (YOLO, SSD, Faster R-CNN)، وتجزئة الصورة، وتقدير الوضعية، والرؤية ثلاثية الأبعاد، والتعلم العميق مع أطر OpenCV.",
        clo_en=[
            "Apply classical image processing techniques for feature extraction.",
            "Implement object detection and segmentation models.",
            "Develop end-to-end computer vision pipelines.",
            "Evaluate computer vision systems on benchmark datasets.",
        ],
        clo_ar=[
            "تطبيق تقنيات معالجة الصور الكلاسيكية لاستخراج الميزات.",
            "تنفيذ نماذج الكشف عن العناصر وتجزئة الصور.",
            "تطوير مسارات رؤية حاسوبية متكاملة من البداية إلى النهاية.",
            "تقييم أنظمة الرؤية الحاسوبية على مجموعات بيانات مرجعية.",
        ],
        coverage={"PLO2": "R", "PLO5": "R", "PLO7": "R"},
    ),
    dict(
        code="MADS205", term="term-2", theory=3, practical=2, credits=4, prerequisites=["MADS104"],
        title_en="AI High Performance Computing",
        title_ar="الحوسبة عالية الأداء للذكاء الاصطناعي",
        desc_en="GPU architecture, CUDA programming, parallel computing models, distributed training (data parallelism, model parallelism), mixed-precision training, model compression, ONNX, TensorRT, and profiling AI workloads.",
        desc_ar="معمارية GPU، وبرمجة CUDA، ونماذج الحوسبة المتوازية، والتدريب الموزّع (توازي البيانات، توازي النماذج)، والتدريب بدقة مختلطة، وضغط النماذج، و ONNX، و TensorRT، وتحليل أداء أحمال عمل الذكاء الاصطناعي.",
        clo_en=[
            "Program GPU kernels for parallel AI computations using CUDA.",
            "Implement distributed training strategies for large models.",
            "Optimize AI models using quantization and pruning techniques.",
            "Profile and benchmark AI system performance.",
        ],
        clo_ar=[
            "برمجة نوى GPU لحسابات الذكاء الاصطناعي المتوازية باستخدام CUDA.",
            "تنفيذ استراتيجيات التدريب الموزّع للنماذج الكبيرة.",
            "تحسين نماذج الذكاء الاصطناعي باستخدام تقنيات التكميم والتقليم.",
            "تحليل أداء أنظمة الذكاء الاصطناعي وقياسه مرجعياً.",
        ],
        coverage={"PLO6": "R", "PLO7": "R"},
    ),
    dict(
        code="MADS206", term="term-2", theory=3, practical=2, credits=4, prerequisites=["MADS106"],
        title_en="Graph Machine Learning",
        title_ar="تعلم الآلة على البيان",
        desc_en="Graph theory for ML, node embeddings (DeepWalk, Node2Vec), graph neural networks (GCN, GAT, GraphSAGE), knowledge graphs, link prediction, community detection, and applications in drug discovery, social networks, and recommendation systems.",
        desc_ar="نظرية البيان لتعلم الآلة، والتضمينات العقدية (DeepWalk, Node2Vec)، والشبكات العصبونية الرسومية (GCN, GAT, GraphSAGE)، ورسوميات المعرفة، وتنبؤ الروابط، والكشف عن المجتمعات، والتطبيقات في اكتشاف الأدوية والشبكات الاجتماعية وأنظمة التوصية.",
        clo_en=[
            "Implement graph neural network architectures for node and graph tasks.",
            "Apply graph embeddings for representation learning.",
            "Build knowledge graph applications.",
            "Deploy GML solutions for real-world domain problems.",
        ],
        clo_ar=[
            "تنفيذ معماريات الشبكات العصبونية على البيان لمهام العقد والبيانات الرسومية.",
            "تطبيق تضمينات البيان في تعلم التمثيل.",
            "بناء تطبيقات رسوميات المعرفة.",
            "نشر حلول تعلم الآلة على البيان لمسائل واقعية في مجالات مختلفة.",
        ],
        coverage={"PLO2": "M", "PLO5": "R", "PLO7": "M"},
    ),
    dict(
        code="MADS301", term="term-3", theory=3, practical=2, credits=4, prerequisites=["MADS202", "MADS106"],
        title_en="Generative AI",
        title_ar="الذكاء الاصطناعي التوليدي",
        desc_en="Variational Autoencoders, GANs, diffusion models, large language models (LLMs), GPT-4/Claude/Gemini architectures, prompt engineering, RLHF, fine-tuning LLMs, multimodal generation, and AI content safety.",
        desc_ar="المشفّرات التلقائية المتغيرة، و GANs، ونماذج الانتشار، ونماذج اللغة الكبيرة (LLMs)، ومعماريات GPT-4/Claude/Gemini، وهندسة التلقين، و RLHF، والضبط الدقيق لنماذج LLMs، والتوليد متعدد الوسائط، وسلامة محتوى الذكاء الاصطناعي.",
        clo_en=[
            "Implement and compare GAN, VAE, and diffusion model architectures.",
            "Apply prompt engineering and fine-tuning techniques to LLMs.",
            "Develop multimodal generative AI applications.",
            "Evaluate generative models for quality, safety, and fairness.",
        ],
        clo_ar=[
            "تنفيذ معماريات GAN و VAE ونماذج الانتشار ومقارنتها.",
            "تطبيق تقنيات هندسة التلقين والضبط الدقيق على نماذج اللغة الكبيرة.",
            "تطوير تطبيقات ذكاء اصطناعي توليدي متعددة الوسائط.",
            "تقييم النماذج التوليدية من حيث الجودة والسلامة والإنصاف.",
        ],
        coverage={"PLO2": "M", "PLO3": "I", "PLO10": "R"},
    ),
    dict(
        code="MADS302", term="term-3", theory=3, practical=2, credits=4, prerequisites=["MADS202", "MADS106"],
        title_en="Agentic AI & Multi-Agent Systems",
        title_ar="الذكاء الاصطناعي الوكيل وأنظمة متعددة الوكلاء",
        desc_en="AI agent architectures (ReAct, Plan-and-Execute), tool use, memory systems, LangChain, LlamaIndex, AutoGen, CrewAI, multi-agent coordination, planning under uncertainty, and autonomous AI systems deployment.",
        desc_ar="معماريات وكلاء الذكاء الاصطناعي (ReAct, Plan-and-Execute)، واستخدام الأدوات، وأنظمة الذاكرة، و LangChain، و LlamaIndex، و AutoGen، و CrewAI، وتنسيق متعدد الوكلاء، والتخطيط في ظل عدم اليقين، ونشر أنظمة الذكاء الاصطناعي المستقلة.",
        clo_en=[
            "Design and implement AI agent pipelines using LangChain or equivalent.",
            "Build multi-agent systems for complex task decomposition.",
            "Integrate external tools and APIs into agentic workflows.",
            "Evaluate reliability, safety, and performance of autonomous AI systems.",
        ],
        clo_ar=[
            "تصميم مسارات وكلاء الذكاء الاصطناعي وتنفيذها باستخدام LangChain أو ما يكافئها.",
            "بناء أنظمة متعددة الوكلاء لتفكيك المهام المعقدة.",
            "دمج الأدوات الخارجية وواجهات البرمجة في مسارات العمل الوكيلة.",
            "تقييم موثوقية أنظمة الذكاء الاصطناعي المستقلة وسلامتها وأدائها.",
        ],
        coverage={"PLO3": "R", "PLO7": "M", "PLO10": "R"},
    ),
    dict(
        code="MADS303", term="term-3", theory=3, practical=2, credits=4, prerequisites=["MADS202"],
        title_en="Advanced Deep Learning",
        title_ar="التعلم العميق المتقدم",
        desc_en="Advanced deep learning topics beyond fundamentals: Parameter-Efficient Fine-Tuning (PEFT) — LoRA, QLoRA, Prefix Tuning, Adapters; self-supervised and contrastive learning (SimCLR, MoCo, BYOL); neural architecture search (NAS); mixture of experts (MoE); state space models (Mamba); graph neural networks for DL; meta-learning and few-shot learning; neural ODEs; and scalable training strategies for very large models.",
        desc_ar="موضوعات التعلم العميق: الضبط الدقيق الفعّال للمعاملات (PEFT)—LoRA، QLoRA، Prefix Tuning، والمحوّلات، والإشراف والتعلّم التبايني (SimCLR, MoCo, BYOL)، والبحث عن المعمارية العصبونية (NAS)، وخليط الخبراء (MoE)، ونماذج فضاء الحالة (Mamba)، والشبكات العصبونية على البيان لتعلم الآلة، والتعلم بالميتا وبعدد قليل من الأمثلة، و ODEs العصبونية، واستراتيجيات التدريب القابلة للتوسع للنماذج الكبيرة جداً.",
        clo_en=[
            "Implement PEFT techniques (LoRA, QLoRA, Adapters) for efficient LLM fine-tuning.",
            "Apply self-supervised and contrastive learning frameworks.",
            "Explore advanced architectures including MoE, state space models, and meta-learning.",
            "Design scalable training strategies for very large deep learning models.",
        ],
        clo_ar=[
            "تنفيذ تقنيات PEFT (LoRA و QLoRA و Adapters) للضبط الدقيق الفعّال لنماذج اللغة الكبيرة.",
            "تطبيق أطر التعلم الذاتي الإشراف والتعلم التبايني.",
            "استكشاف المعماريات المتقدمة بما فيها خليط الخبراء ونماذج فضاء الحالة والتعلم بالميتا.",
            "تصميم استراتيجيات تدريب قابلة للتوسع لنماذج التعلم العميق الكبيرة جداً.",
        ],
        coverage={"PLO2": "M", "PLO3": "R", "PLO6": "R"},
    ),
    dict(
        code="MADS304", term="term-3", theory=3, practical=2, credits=4, prerequisites=["MADS202", "MADS206"],
        title_en="TinyML & Edge AI",
        title_ar="الذكاء الاصطناعي على الأجهزة الطرفية (TinyML)",
        desc_en="Model quantization, pruning, knowledge distillation, neural architecture search, TensorFlow Lite, ONNX Runtime, deployment on microcontrollers (Arduino, STM32, Raspberry Pi), Edge Impulse, and federated learning.",
        desc_ar="تكميم النماذج، والتقليم، وتقطير المعرفة، والبحث عن المعمارية العصبونية، و TensorFlow Lite، و ONNX Runtime، والنشر على المتحكمات الصغرية (Arduino, STM32, Raspberry Pi)، و Edge Impulse، والتعلم الفيدرالي.",
        clo_en=[
            "Apply model compression techniques for edge deployment.",
            "Deploy AI models on microcontrollers and embedded systems.",
            "Implement federated learning for privacy-preserving AI.",
            "Benchmark TinyML models for accuracy-latency tradeoffs.",
        ],
        clo_ar=[
            "تطبيق تقنيات ضغط النماذج للنشر على الأجهزة الطرفية.",
            "نشر نماذج الذكاء الاصطناعي على المتحكمات الصغرية والأنظمة المضمّنة.",
            "تنفيذ التعلم الفيدرالي لذكاء اصطناعي يحافظ على الخصوصية.",
            "قياس نماذج TinyML مرجعياً للموازنة بين الدقة وزمن الاستجابة.",
        ],
        coverage={"PLO6": "M", "PLO7": "M"},
    ),
    dict(
        code="MADS305", term="term-3", theory=3, practical=2, credits=4, prerequisites=["MADS203"],
        title_en="Vector Databases & Retrieval-Augmented Generation",
        title_ar="قواعد البيانات المتجهة والتوليد المعزز بالاسترجاع",
        desc_en="Vector embeddings, similarity search, ANN algorithms (HNSW, IVF), vector databases (Pinecone, Weaviate, Milvus, Chroma), RAG architectures, hybrid search, chunking strategies, and production RAG system design.",
        desc_ar="التضمينات المتجهة، والبحث عن التشابه، وخوارزميات ANN (HNSW, IVF)، وقواعد البيانات المتجهة (Pinecone, Weaviate, Milvus, Chroma)، ومعماريات RAG، والبحث الهجين، واستراتيجيات التقطيع، وتصميم أنظمة RAG الإنتاجية.",
        clo_en=[
            "Design and implement vector indexing and similarity search systems.",
            "Build end-to-end RAG pipelines with LLMs and vector stores.",
            "Evaluate and optimize retrieval quality in RAG systems.",
            "Deploy production-grade vector database solutions.",
        ],
        clo_ar=[
            "تصميم أنظمة الفهرسة المتجهة والبحث عن التشابه وتنفيذها.",
            "بناء مسارات RAG متكاملة باستخدام نماذج اللغة الكبيرة ومخازن المتجهات.",
            "تقييم جودة الاسترجاع في أنظمة RAG وتحسينها.",
            "نشر حلول قواعد بيانات متجهة بمستوى الإنتاج.",
        ],
        coverage={"PLO3": "R", "PLO4": "M", "PLO5": "R"},
    ),
    dict(
        code="MADS306", term="term-3", theory=3, practical=2, credits=4, prerequisites=["MADS202", "MADS204"],
        title_en="Multimodal AI & Cross-Modal Learning",
        title_ar="الذكاء الاصطناعي متعدد الوسائط والتعلم عبر الأنماط",
        desc_en="Foundations of multimodal learning; vision-language models (CLIP, ALIGN, Florence); image captioning and visual question answering (VQA); text-to-image generation (Stable Diffusion, DALL-E); audio-visual models; multimodal transformers (Flamingo, LLaVA, GPT-4V); cross-modal retrieval; multimodal fusion strategies; video understanding; and evaluation metrics for multimodal systems.",
        desc_ar="أسس التعلم متعدد الوسائط، نماذج الرؤية واللغة (CLIP, ALIGN, Florence)، التعليق على الصور والإجابة على الأسئلة البصرية (VQA)، توليد الصور من النصوص (Stable Diffusion, DALL-E)، النماذج الصوتية-البصرية، المحوّلات متعددة الوسائط (Flamingo, LLaVA, GPT-4V)، الاسترجاع عبر الأنماط، استراتيجيات الدمج متعدد الوسائط، فهم الفيديو، معايير تقييم الأنظمة متعددة الوسائط.",
        clo_en=[
            "Implement vision-language models such as CLIP for cross-modal tasks.",
            "Build multimodal pipelines for VQA, image captioning, and text-to-image generation.",
            "Apply multimodal fusion strategies and cross-modal retrieval techniques.",
            "Evaluate multimodal AI systems using domain-appropriate metrics.",
        ],
        clo_ar=[
            "تنفيذ نماذج الرؤية واللغة مثل CLIP للمهام عبر الأنماط.",
            "بناء مسارات متعددة الوسائط للإجابة على الأسئلة البصرية والتعليق على الصور وتوليد الصور من النصوص.",
            "تطبيق استراتيجيات الدمج متعدد الوسائط وتقنيات الاسترجاع عبر الأنماط.",
            "تقييم أنظمة الذكاء الاصطناعي متعددة الوسائط باستخدام مقاييس ملائمة للمجال.",
        ],
        coverage={"PLO3": "R", "PLO5": "M", "PLO7": "M"},
    ),
    dict(
        code="MADS401", term="term-4", theory=3, practical=2, credits=4, prerequisites=["MADS301", "MADS302"],
        title_en="AI Ethics, Governance & Research Methodology",
        title_ar="أخلاقيات الذكاء الاصطناعي والحوكمة ومنهجية البحث",
        desc_en="Research design, literature review, scientific writing, AI ethics frameworks (fairness, accountability, transparency), algorithmic bias, data privacy (GDPR, AI Act), AI governance policies, and responsible deployment practices.",
        desc_ar="تصميم البحث، ومراجعة الأدبيات، والكتابة العلمية، وأطر أخلاقيات الذكاء الاصطناعي (الإنصاف والمساءلة والشفافية)، والتحيز الخوارزمي، وخصوصية البيانات (GDPR وقانون الذكاء الاصطناعي)، وسياسات حوكمة الذكاء الاصطناعي، وممارسات النشر المسؤول.",
        clo_en=[
            "Conduct systematic literature reviews in AI and data science.",
            "Apply ethical frameworks to evaluate AI system designs.",
            "Communicate research findings through scientific writing.",
            "Analyze AI governance and compliance requirements.",
        ],
        clo_ar=[
            "إجراء مراجعات منهجية للأدبيات في الذكاء الاصطناعي وعلوم البيانات.",
            "تطبيق الأطر الأخلاقية لتقييم تصاميم أنظمة الذكاء الاصطناعي.",
            "عرض نتائج البحث من خلال الكتابة العلمية.",
            "تحليل متطلبات حوكمة الذكاء الاصطناعي والامتثال.",
        ],
        coverage={"PLO8": "I", "PLO9": "R", "PLO10": "M"},
    ),
    dict(
        code="MADS402", term="term-4", theory=6, practical=4, credits=8, prerequisites=["MADS303", "MADS305"],
        title_en="Advanced Topics in AI (Seminar)",
        title_ar="موضوعات متقدمة في الذكاء الاصطناعي (سيمينار)",
        desc_en="Current research frontiers in AI including foundation models, world models, neuromorphic computing, AI for science (drug discovery, climate, genomics), AI safety and alignment, and emerging paradigms. Includes invited lectures and student presentations.",
        desc_ar="آفاق البحث الحالية في الذكاء الاصطناعي تشمل النماذج التأسيسية، ونماذج العالم، والحوسبة العصبية الشكل، والذكاء الاصطناعي للعلوم (اكتشاف الأدوية، المناخ، الجينوم)، وسلامة ومواءمة الذكاء الاصطناعي، والنماذج الناشئة. يتضمن محاضرات مدعوّة وعروض طلابية.",
        clo_en=[
            "Critically review and present recent AI research papers.",
            "Identify open research problems in AI and data science.",
            "Synthesize knowledge across AI sub-fields for cross-domain applications.",
            "Communicate advanced AI concepts to diverse audiences.",
        ],
        clo_ar=[
            "مراجعة الأوراق البحثية الحديثة في الذكاء الاصطناعي نقدياً وعرضها.",
            "تحديد المسائل البحثية المفتوحة في الذكاء الاصطناعي وعلوم البيانات.",
            "الربط بين معارف فروع الذكاء الاصطناعي المختلفة لتطبيقات متعددة المجالات.",
            "إيصال مفاهيم الذكاء الاصطناعي المتقدمة إلى جماهير متنوعة.",
        ],
        coverage={"PLO8": "R", "PLO9": "M", "PLO10": "R"},
    ),
    dict(
        code="MADS403", term="term-4", theory=3, practical=2, credits=4, prerequisites=["MADS302", "MADS304"],
        title_en="AI Product Engineering",
        title_ar="هندسة منتجات الذكاء الاصطناعي",
        desc_en="End-to-end AI product lifecycle: experiment tracking (MLflow, W&B), data versioning (DVC), CI/CD for ML, feature stores, model serving (FastAPI, BentoML, Triton), A/B testing, shadow and canary deployments, drift detection, model monitoring, AI security (adversarial attacks, model stealing, prompt injection), compliance engineering, and industry case studies in production AI.",
        desc_ar="دورة حياة منتج الذكاء الاصطناعي الكاملة: تتبع التجارب (MLflow, W&B)، وإصدارات البيانات (DVC)، وخدمة CI/CD لتعلم الآلة، ومخازن الميزات، وخدمة النموذج (FastAPI, BentoML, Triton)، واختبار A/B، والنشر الظلي، والكشف عن الانجراف، ومراقبة النماذج، وأمن الذكاء الاصطناعي (الهجمات الخصمية، وسرقة النماذج، وحقن المطالبات)، وهندسة الامتثال، ودراسات الحالة الصناعية في إنتاج الذكاء الاصطناعي.",
        clo_en=[
            "Design and implement full MLOps pipelines with experiment tracking and CI/CD.",
            "Deploy and monitor AI models at scale with drift detection and alerting.",
            "Implement AI security measures against adversarial and injection threats.",
            "Apply responsible AI practices in production product development.",
        ],
        clo_ar=[
            "تصميم مسارات MLOps كاملة وتنفيذها مع تتبع التجارب و CI/CD.",
            "نشر نماذج الذكاء الاصطناعي على نطاق واسع ومراقبتها مع الكشف عن الانجراف والتنبيه.",
            "تنفيذ تدابير أمن الذكاء الاصطناعي في مواجهة الهجمات الخصمية وتهديدات الحقن.",
            "تطبيق ممارسات الذكاء الاصطناعي المسؤول في تطوير المنتجات الإنتاجية.",
        ],
        coverage={"PLO1": "M", "PLO3": "M", "PLO7": "M", "PLO9": "M", "PLO10": "M"},
    ),
    dict(
        code="MADS404", term="term-4", theory=3, practical=2, credits=4, prerequisites=["MADS306"],
        title_en="Reinforcement Learning & Decision Making",
        title_ar="التعلم المعزز واتخاذ القرار",
        desc_en="Markov Decision Processes, dynamic programming, Monte Carlo methods, temporal difference learning, Q-learning, Deep Q-Networks (DQN), policy gradient methods, Proximal Policy Optimization (PPO), multi-armed bandits, and applications in robotics and game playing.",
        desc_ar="عمليات القرار ماركوف، والبرمجة الديناميكية، وأساليب مونت كارلو، والتعلم الفرقي الزمني، Q-Learning، وشبكات DQN، وأساليب تدرج السياسة، والبانديت متعدد الأذرع، والتطبيقات في الروبوتات والألعاب.",
        clo_en=[
            "Model sequential decision problems as MDPs.",
            "Implement value-based and policy-based RL algorithms.",
            "Apply deep RL to control and game-playing environments.",
            "Evaluate RL agent performance and convergence.",
        ],
        clo_ar=[
            "نمذجة مسائل القرار المتتابعة على شكل عمليات قرار ماركوف.",
            "تنفيذ خوارزميات التعلم المعزز المبنية على القيمة والمبنية على السياسة.",
            "تطبيق التعلم المعزز العميق في بيئات التحكم والألعاب.",
            "تقييم أداء وكلاء التعلم المعزز وتقاربهم.",
        ],
        coverage={"PLO2": "M", "PLO3": "R", "PLO6": "R"},
    ),
    dict(
        code="MADS405", term="term-4", theory=4, practical=8, credits=8, prerequisites=[],
        prerequisite_note={"en": "All Semester 1-3 courses", "ar": "إنجاز جميع مقررات الفصول 1-3"},
        title_en="Master Graduation Project",
        title_ar="مشروع تخرج الماجستير",
        desc_en="Literature review, problem formulation, dataset collection, methodology design, preliminary experiments, full implementation, experimentation, results analysis, and academic paper writing. Supervised by a faculty advisor.",
        desc_ar="مراجعة الأدبيات، وصياغة المشكلة، وجمع البيانات، وتصميم المنهجية، والتجارب الأولية، وتقرير التقدم، التنفيذ الكامل، والتجارب، وتحليل النتائج، وكتابة الورقة الأكاديمية بإشراف مشرف أكاديمي.",
        clo_en=[
            "Define a clear AI/data science research problem and objectives.",
            "Conduct a comprehensive literature review.",
            "Design an appropriate research methodology, implement and validate the proposed AI/data science solution, and analyze and critically discuss experimental results.",
            "Write a complete Master's thesis adhering to academic standards and defend it professionally before an examination committee.",
        ],
        clo_ar=[
            "تحديد مسألة بحثية واضحة وأهدافها في الذكاء الاصطناعي وعلوم البيانات.",
            "إجراء مراجعة شاملة للأدبيات.",
            "تصميم منهجية بحث ملائمة، وتنفيذ الحل المقترح والتحقق منه، وتحليل النتائج التجريبية ومناقشتها نقدياً.",
            "كتابة رسالة ماجستير كاملة وفق المعايير الأكاديمية والدفاع عنها باحترافية أمام لجنة الحكم.",
        ],
        grading={
            "en": "Final presentation 40%, thesis 30%, Seminar 2 15%, Seminar 1 15%.",
            "ar": "العرض النهائي 40%، الأطروحة 30%، سيمينار 2 15%، سيمينار 1 15%.",
        },
        coverage={"PLO1": "M", "PLO3": "M", "PLO8": "M", "PLO9": "M", "PLO10": "R"},
    ),
]

OUTCOMES = [
    ("PLO1", "Apply advanced mathematical, statistical, and computational foundations to solve complex AI and data science problems.", "تطبيق أسس رياضية وإحصائية وحسابية متقدمة لحل مسائل معقدة في الذكاء الاصطناعي وعلوم البيانات."),
    ("PLO2", "Design and implement machine learning and deep learning models for real-world applications.", "تصميم وتنفيذ نماذج تعلم الآلة والتعلم العميق للتطبيقات الواقعية."),
    ("PLO3", "Develop and deploy generative AI and agentic AI systems using state-of-the-art architectures.", "تطوير ونشر أنظمة الذكاء الاصطناعي التوليدي والذكاء الاصطناعي الوكيل باستخدام أحدث المعماريات."),
    ("PLO4", "Engineer scalable big data pipelines and manage diverse data storage paradigms including NoSQL and Vector databases.", "هندسة مسارات البيانات الضخمة القابلة للتوسع وإدارة نماذج تخزين البيانات المتنوعة بما فيها قواعد بيانات المتجهات و NoSQL."),
    ("PLO5", "Apply natural language processing and computer vision techniques to extract insights from unstructured data.", "تطبيق تقنيات معالجة اللغة الطبيعية والرؤية الحاسوبية لاستخلاص الرؤى من البيانات غير المنظمة."),
    ("PLO6", "Optimize AI workloads on high-performance computing and edge/embedded environments.", "تحسين أعباء عمل الذكاء الاصطناعي على بيئات الحوسبة عالية الأداء والحافة/المضمّن."),
    ("PLO7", "Evaluate, select, and integrate appropriate AI and data science tools, frameworks, and platforms for diverse engineering domains.", "تقييم واختيار ودمج أدوات وأطر ومنصات الذكاء الاصطناعي وعلوم البيانات المناسبة لمجالات هندسية متنوعة."),
    ("PLO8", "Conduct original research and produce a professional Master's thesis demonstrating mastery of AI and data science methodologies.", "إجراء بحث أصيل وإنتاج رسالة ماجستير تأهيل وتخصص تُظهر الإتقان في منهجيات الذكاء الاصطناعي وعلوم البيانات."),
    ("PLO9", "Communicate technical findings effectively to both technical and non-technical stakeholders in professional settings.", "التواصل الفعّال عن النتائج التقنية مع الأطراف المعنية التقنية وغير التقنية في البيئات المهنية."),
    ("PLO10", "Apply ethical, legal, and societal considerations in the development and deployment of AI systems.", "تطبيق الاعتبارات الأخلاقية والقانونية والاجتماعية في تطوير ونشر أنظمة الذكاء الاصطناعي."),
]

ASSESSMENT = [
    ("Final Examination", "الامتحان النهائي", "50%", "50%",
     "Comprehensive written exam covering all course material.",
     "امتحان مكتوب شامل يغطي جميع مادة المقرر."),
    ("Projects & Practical Work", "المشاريع والعمل التطبيقي", "35%", "35%",
     "Individual and group projects, lab assignments, Jupyter notebooks, GitHub submissions, presentations.",
     "مشاريع فردية وجماعية، ومهام مختبرية، ودفاتر Jupyter، وتسليمات GitHub، وعروض."),
    ("Quizzes, Homework & Participation", "الاختبارات القصيرة والواجبات والمشاركة", "15%", "15%",
     "Regular quizzes, homework assignments, class participation, and peer reviews.",
     "اختبارات دورية، وواجبات منزلية، ومشاركة في الفصل، ومراجعات الأقران."),
    ("Graduation Project Defense", "مناقشة مشروع التخرج", "Semester 4", "الفصل 4",
     "Oral defense before a three-member examination committee. Graded on originality, methodology, results, and presentation quality.",
     "مناقشة شفهية أمام لجنة امتحانية مؤلفة من ثلاثة أعضاء. يُقيَّم على الأصالة والمنهجية والنتائج وجودة العرض."),
]

PROJECT_INTRO = {
    "ar": "يُعدّ مشروع تخرج الماجستير مقرراً كباقي المقررات، وهو يهدف إلى تعليم الطلاب معالجة موضوع هندسي تخصصي في الذكاء الاصطناعي وعلوم البيانات وتنفيذ الحلّ الذي يتوصلون إليه. يجري تنفيذ مشروع تخرج الماجستير وفق الآتي:",
    "en": "The Master's graduation project is a course like any other. It teaches students to tackle a specialized engineering topic in artificial intelligence and data science and to implement the solution they reach. The project is carried out as follows:",
}

PROJECT_RULES = [
    ("يُعِدّ الطالب مشروعاً في موضوع يقرّه مجلس الكلية، ويقدّم بنتيجة عمله في هذا المشروع تقريراً تجري مناقشته أمام لجنة حكم يشكّلها مجلس الكلية لهذا الغرض.",
     "The student prepares a project on a topic approved by the Faculty Council and submits a report on the work, which is defended before an examination committee the Faculty Council forms for this purpose."),
    ("يجب أن يكون مشروع التخرج من ضمن الاختصاصات الدقيقة للبرنامج.",
     "The graduation project must fall within the program's specializations."),
    ("يُنجز المشروع بإشراف أحد أعضاء الهيئة التعليمية، أو من في حكمهم، أو أحد المختصين ممن يوافق عليه مجلس الكلية من حملة شهادة الدكتوراه.",
     "The project is supervised by a member of the teaching staff or equivalent, or by a PhD-holding specialist approved by the Faculty Council."),
    ("يُشترط لتسجيل مشروع الماجستير أن يكون الطالب قد أنجز 72 ساعة معتمدة على الأقل.",
     "To register for the Master's project, the student must have completed at least 72 credit hours."),
    ("يجري تحكيم مشاريع تخرج الماجستير من قبل لجنة تحكيم ثلاثية يشكّلها مجلس الكلية، وفي المواعيد التي يحدّدها مجلس الكلية.",
     "Master's graduation projects are assessed by a three-member committee formed by the Faculty Council, on dates the Council sets."),
    ("تقوم لجنة الحكم بتقويم المشروع وفقاً للقواعد التي يحددها مجلس الكلية، ويعد الطالب ناجحاً في المشروع إذا حصل على درجة لا تقل عن 60 من 100.",
     "The committee grades the project under rules set by the Faculty Council. The student passes with a mark of at least 60 out of 100."),
    ("يجوز للجنة الحكم إعطاء مهلة لا تتجاوز الشهر من تاريخ المناقشة لإجراء التعديلات (في حال الضرورة)، ولا يتم في هذه الحالة إعادة المناقشة.",
     "When necessary, the committee may allow up to one month from the defense date for revisions; the defense is not repeated in that case."),
    ("يعدّ الطالب راسباً في المشروع في حال عدم حصوله على علامة 60 على الأقل أو في حال عدم موافقة اللجنة على التعديلات التي تطلب منه خلال أقل من شهر بعد المناقشة، ويعيد الطالب التسجيل على المشروع من جديد ولمرة واحدة.",
     "A student fails the project with a mark below 60, or if the committee does not accept the requested revisions within one month of the defense. The student may then register for the project again, once."),
]

MAJORS = [
    ("الهندسة المعلوماتية", "Informatics Engineering"),
    ("الهندسة الميكانيكية والكهربائية (هندسة حواسيب)", "Mechanical and Electrical Engineering (Computer Engineering)"),
    ("هندسة الحاسوب والمعلوماتية", "Computer and Informatics Engineering"),
    ("هندسة تكنولوجيا المعلومات والاتصالات (تقانة المعلومات)", "Information and Communication Technology Engineering (Information Technology)"),
    ("هندسة المعلوماتية والاتصالات (نظم المعلومات، برمجيات، ذكاء صنعي)", "Informatics and Communications Engineering (Information Systems, Software, Artificial Intelligence)"),
    ("هندسة البرمجيات ونظم المعلومات", "Software Engineering and Information Systems"),
    ("هندسة كهرباء (هندسة تحكم آلي وحواسيب)", "Electrical Engineering (Automatic Control and Computer Engineering)"),
    ("هندسة إلكترون (هندسة حواسيب وهندسة الحاسبات والتحكم الآلي)", "Electronic Engineering (Computer Engineering, Computers and Automatic Control)"),
    ("العلوم (اختصاص إنفورماتيك - الرياضيات التطبيقية والمعلوماتية)", "Science (Informatics, Applied Mathematics and Informatics)"),
    ("هندسة المعلومات (تكنولوجيا المعلومات)", "Information Engineering (Information Technology)"),
    ("دبلوم هندسة المعهد العالي للعلوم التطبيقية والتكنولوجيا (هندسة معلوميات/معلوماتية/نظم معلوماتية)", "Engineering Diploma of the Higher Institute for Applied Sciences and Technology (Informatics / Information Systems Engineering)"),
    ("علوم الحاسوب", "Computer Science"),
    ("هندسة نظم الحاسوب", "Computer Systems Engineering"),
    ("نظم المعلومات / إدارة نظم وتقانات المعلومات / نظم المعلومات الإدارية (MIS)", "Information Systems / Information Systems and Technology Management / Management Information Systems (MIS)"),
    ("إجازة في المعلوماتية UOG", "Bachelor's in Informatics (UOG)"),
    ("الأكاديمية العربية للعلوم والتكنولوجيا والنقل البحري (اختصاص هندسة الحاسب)", "Arab Academy for Science, Technology and Maritime Transport (Computer Engineering)"),
    ("هندسة الحواسيب (أتمتة وتحكم) / هندسة الاتصالات / هندسة المعلومات والاتصالات / هندسة الشبكات / الهندسة الطبية", "Computer Engineering (Automation and Control) / Communications Engineering / Information and Communications Engineering / Network Engineering / Biomedical Engineering"),
]

LOCALE_TEXT = {
    "ar": {
        "title": "ماجستير تأهيل وتخصص في الذكاء الاصطناعي وعلوم البيانات",
        "summary": "برنامج ماجستير مهني في كلية هندسة الذكاء الاصطناعي، مدته سنتان دراسيتان على أربعة فصول، يجمع أسس الذكاء الاصطناعي وهندسة البيانات مع الذكاء الاصطناعي التوليدي والوكيل وينتهي بمشروع تخرج بحثي.",
        "seoTitle": "ماجستير الذكاء الاصطناعي وعلوم البيانات | كلية هندسة الذكاء الاصطناعي",
        "seoDescription": "الخطة الدراسية لبرنامج ماجستير تأهيل وتخصص في الذكاء الاصطناعي وعلوم البيانات في الجامعة السورية الخاصة: المقررات، ومخرجات التعلم، وشروط القبول، وأحكام مشروع التخرج.",
        "overview": [
            "يُحدث في كلية هندسة الذكاء الاصطناعي برنامج ماجستير تأهيل وتخصص في الذكاء الاصطناعي وعلوم البيانات.",
            "تمنح الجامعة السورية الخاصة بناءً على اقتراح مجلس كلية هندسة الذكاء الاصطناعي فيها درجة ماجستير تأهيل وتخصص في الذكاء الاصطناعي وعلوم البيانات إضافة إلى درجات الإجازة التي تمنحها الكلية.",
            "مدة الدراسة سنتان دراسيتان موزعة على أربعة مستويات (فصول دراسية) بعد اجتياز الطالب 100 ساعة معتمدة كحد أدنى.",
        ],
        "facts": [
            ("الدرجة العلمية", "ماجستير تأهيل وتخصص"),
            ("مدة الدراسة", "سنتان (4 فصول)"),
            ("الساعات المعتمدة", "100 ساعة كحد أدنى"),
            ("المقررات", "23 مقرراً"),
        ],
        "coding": "ترميز المقررات: حرفان للبرنامج (MADS)، وخانة واحدة للمستوى، وخانتان لتسلسل المقرر (من 01 إلى 99).",
        "admissionIntro": "يُشترط في قبول المرشح لتحضير درجة ماجستير تأهيل وتخصص في الذكاء الاصطناعي وعلوم البيانات أن يكون حاصلاً على إجازة جامعية بمعدّل تخرج جيد على الأقل من إحدى الجامعات الحكومية، أو الخاصة، أو برامج التقانة من الجامعة الافتراضية السورية، أو الكليات التطبيقية، أو الجامعات غير السورية المعترف بها، وذلك في أحد الاختصاصات التالية:",
        "criteria": [
            ("معدل التخرج", "80%", "معدل التخرج في الدرجة العلمية المقبول على أساسها المرشح."),
            ("اختبار اللغة الإنكليزية", "20%", "نتيجة اختبار اللغة الإنكليزية الذي تجريه الجامعة."),
        ],
        "admissionNote": "يُفوّض مجلس الجامعة بتعديل معدلات القبول في بداية كل فصل دراسي، تبعاً لسياسة الاستيعاب المعتمدة والقدرة الاستيعابية للجامعة.",
        "requirements": [
            "أن ينجح في جميع المقرّرات.",
            "أن ينجح في مشروع الماجستير.",
            "أن يُسدد الرسوم والأقساط المالية المترتبة عليه وفقاً للنظام المالي.",
        ],
        "teachingNote": "يُعتمد قرار مجلس التعليم العالي رقم /21/ تاريخ 28/9/2014م وتعديلاته كأساس لنظام التدريس والامتحانات والتقويم الموحّد للجامعات السورية الخاصة التي تعتمد نظام الساعات المعتمدة.",
        "coverageLegend": "I = تقديم، R = تعزيز، M = إتقان",
    },
    "en": {
        "title": "Professional Master's in Artificial Intelligence & Data Sciences",
        "summary": "A professional master's program at the Faculty of Artificial Intelligence Engineering: two academic years over four semesters, from AI and data engineering foundations to generative and agentic AI, ending in a research graduation project.",
        "seoTitle": "Master's in AI & Data Sciences | Faculty of AI Engineering",
        "seoDescription": "Study plan of the Professional Master's in Artificial Intelligence & Data Sciences at Syrian Private University: courses, learning outcomes, admission requirements and graduation project rules.",
        "overview": [
            "The Faculty of Artificial Intelligence Engineering offers a Professional Master's Degree Program in Artificial Intelligence & Data Sciences.",
            "On the proposal of the Faculty Council, Syrian Private University awards the Professional Master's Degree in Artificial Intelligence & Data Sciences, in addition to the bachelor's degrees the faculty awards.",
            "Study lasts two academic years over four levels (semesters), with a minimum of 100 credit hours.",
        ],
        "facts": [
            ("Degree", "Professional Master's"),
            ("Duration", "2 years (4 semesters)"),
            ("Credit hours", "100 minimum"),
            ("Courses", "23 courses"),
        ],
        "coding": "Course codes: two letters for the program (MADS), one digit for the level, and two digits for the course sequence (01 to 99).",
        "admissionIntro": "Candidates for the Professional Master's in Artificial Intelligence & Data Sciences must hold a bachelor's degree with at least a Good graduation average from a public or private university, the technology programs of the Syrian Virtual University, an applied college, or a recognized non-Syrian university, in one of the following majors:",
        "criteria": [
            ("Graduation average", "80%", "The graduation average of the degree on which the candidate is admitted."),
            ("English language test", "20%", "The result of the English language test held by the university."),
        ],
        "admissionNote": "The University Council may adjust admission averages at the start of each semester, according to the approved intake policy and the university's capacity.",
        "requirements": [
            "Pass all courses.",
            "Pass the Master's project.",
            "Pay the fees and installments due under the university's financial regulations.",
        ],
        "teachingNote": "Teaching, examinations and assessment follow Higher Education Council Decision No. 21 of 28/9/2014 and its amendments, the unified system for Syrian private universities that use credit hours.",
        "coverageLegend": "I = Introduced, R = Reinforced, M = Mastered",
    },
}


def build(locale: str) -> dict:
    other = "ar" if locale == "en" else "en"
    text = LOCALE_TEXT[locale]
    terms = []
    for term in TERMS:
        courses = []
        for course in (c for c in COURSES if c["term"] == term["id"]):
            courses.append({
                "code": course["code"],
                "title": course["title_" + locale],
                "titleAlt": course["title_" + other],
                "credits": course["credits"],
                "theory": course["theory"],
                "practical": course["practical"],
                "prerequisites": course["prerequisites"],
                "prerequisiteNote": course.get("prerequisite_note", {}).get(locale, ""),
                "description": course["desc_" + locale],
                "outcomes": [{"text": clo} for clo in course["clo_" + locale]],
                "grading": course.get("grading", GRADING)[locale],
                "coverage": course["coverage"],
            })
        terms.append({
            "id": term["id"],
            "label": term["label"][locale],
            "theme": term["theme"][locale],
            "courses": courses,
        })

    ar = locale == "ar"
    return {
        "title": text["title"],
        "summary": text["summary"],
        "seoTitle": text["seoTitle"],
        "seoDescription": text["seoDescription"],
        "payload": {
            "overview": [{"text": p} for p in text["overview"]],
            "facts": [{"label": label, "value": value} for label, value in text["facts"]],
            "coding": text["coding"],
            "terms": terms,
            "outcomes": [{"code": code, "text": ar_text if ar else en_text} for code, en_text, ar_text in OUTCOMES],
            "coverageLegend": text["coverageLegend"],
            "assessment": [
                {"type": a_ar if ar else a_en, "weight": w_ar if ar else w_en, "description": d_ar if ar else d_en}
                for a_en, a_ar, w_en, w_ar, d_en, d_ar in ASSESSMENT
            ],
            "projectIntro": PROJECT_INTRO[locale],
            "projectRules": [{"text": r_ar if ar else r_en} for r_ar, r_en in PROJECT_RULES],
            "admission": {
                "intro": text["admissionIntro"],
                "majors": [{"text": m_ar if ar else m_en} for m_ar, m_en in MAJORS],
                "criteria": [{"label": label, "weight": weight, "description": desc} for label, weight, desc in text["criteria"]],
                "note": text["admissionNote"],
            },
            "degreeRequirements": [{"text": r} for r in text["requirements"]],
            "teachingNote": text["teachingNote"],
        },
    }


if __name__ == "__main__":
    data = {"ar": build("ar"), "en": build("en")}
    out = Path(__file__).with_name("artificial-intelligence.json")
    out.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"wrote {out}")
